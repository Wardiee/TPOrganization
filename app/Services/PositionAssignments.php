<?php

namespace App\Services;

use App\Enums\Branch;
use App\Enums\DatePrecision;
use App\Enums\HistoryKind;
use App\Models\HistoryEvent;
use App\Models\Member;
use App\Models\Position;
use App\Models\PositionAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Changes who holds a seat. History is never deleted: the current holder's assignment is ended with a
 * date, a new one is opened, and the change is written to the structure history.
 */
class PositionAssignments
{
    /**
     * Give the seat to a member, or leave it empty when $member is null.
     *
     * With $moveFromOtherSeats, the member's other TROP/TROPS seats are ended too, so moving someone
     * from TROPS-4 to TROP-13 (a promotion to long-term member) is one step.
     */
    public function assign(
        Position $position,
        ?Member $member,
        CarbonImmutable $on,
        ?string $note = null,
        bool $moveFromOtherSeats = true,
    ): void {
        DB::transaction(function () use ($position, $member, $on, $note, $moveFromOtherSeats): void {
            $previous = $this->end($position->assignments()->current()->with('member')->get(), $on);

            $movedFrom = collect();

            if ($member !== null && $moveFromOtherSeats) {
                $otherSeats = $member->currentAssignments()
                    ->where('position_id', '!=', $position->id)
                    ->whereRelation('position', fn ($query) => $query->whereNotNull('branch'))
                    ->with('position')
                    ->get();

                $movedFrom = $this->end($otherSeats, $on)->map(fn (PositionAssignment $assignment) => $assignment->position->code);
            }

            if ($member !== null) {
                $position->assignments()->create([
                    'member_id' => $member->id,
                    'started_on' => $on,
                    'source' => 'Changed in the app',
                ]);
            }

            HistoryEvent::create([
                'happened_on' => $on,
                'date_precision' => DatePrecision::Day,
                'title' => $this->title($position, $member, $movedFrom),
                'detail' => $this->detail($previous, $note),
                'kind' => HistoryKind::Org,
            ]);
        });
    }

    /**
     * The code the next new seat in a branch would get, e.g. "TROP-16".
     */
    public function nextSeatCode(Branch $branch): string
    {
        $highest = Position::query()->where('branch', $branch)->get()->map(fn (Position $position) => $position->number() ?? 0)->max() ?? 0;

        return $branch->value.'-'.($highest + 1);
    }

    /**
     * Add the next numbered seat to a branch (e.g. TROPS-16) as membership grows, and note it in history.
     */
    public function addSeat(Branch $branch, ?CarbonImmutable $on = null): Position
    {
        return DB::transaction(function () use ($branch, $on): Position {
            $code = $this->nextSeatCode($branch);

            $position = Position::create([
                'code' => $code,
                'title' => $code,
                'branch' => $branch,
                'sort' => (int) Position::query()->where('branch', $branch)->max('sort') + 1,
            ]);

            HistoryEvent::create([
                'happened_on' => $on ?? CarbonImmutable::today(),
                'date_precision' => DatePrecision::Day,
                'title' => "New seat added: {$code}",
                'detail' => $branch === Branch::Trop ? 'TROP · Main (long-term members).' : 'TROPS · Ext (newer members).',
                'kind' => HistoryKind::Org,
            ]);

            return $position;
        });
    }

    /**
     * @param  Collection<int, PositionAssignment>  $assignments
     * @return Collection<int, PositionAssignment>
     */
    private function end(Collection $assignments, CarbonImmutable $on): Collection
    {
        $assignments->each(fn (PositionAssignment $assignment) => $assignment->update(['ended_on' => $on]));

        return $assignments;
    }

    /**
     * @param  Collection<int, string>  $movedFrom
     */
    private function title(Position $position, ?Member $member, Collection $movedFrom): string
    {
        return match (true) {
            $member === null => "{$position->code} left empty",
            $movedFrom->isNotEmpty() => "{$member->full_name} moved from {$movedFrom->implode(', ')} to {$position->code}",
            default => "{$member->full_name} assigned to {$position->code}",
        };
    }

    /**
     * @param  Collection<int, PositionAssignment>  $previous
     */
    private function detail(Collection $previous, ?string $note): ?string
    {
        $holders = $previous
            ->map(fn (PositionAssignment $assignment) => $assignment->member_id === null ? $assignment->name_as_listed : $assignment->member->full_name)
            ->filter();

        $parts = collect([
            $holders->isEmpty() ? null : 'Previously held by '.$holders->implode(', ').'.',
            filled($note) ? trim($note) : null,
        ])->filter();

        return $parts->isEmpty() ? null : $parts->implode(' ');
    }
}
