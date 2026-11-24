<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\MemberStatus;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Attendance figures for the Reports page and its downloads, for all time or one calendar year.
 *
 * Uses the same rules as the rest of the app: turnout is present ÷ active members expected (minus
 * those marked "not required"); a member's rate is present ÷ activities they were required at.
 * Activities without a date only appear under "All time".
 */
class AttendanceReport
{
    /** @var Collection<int, Activity>|null */
    private ?Collection $activitiesCache = null;

    private function __construct(public readonly ?int $year) {}

    public static function forYear(?int $year): self
    {
        return new self($year);
    }

    /**
     * Years that have at least one dated activity, newest first.
     *
     * @return list<int>
     */
    public static function years(): array
    {
        return array_values(Activity::query()->whereNotNull('starts_on')->pluck('starts_on')
            ->map(fn ($date): int => $date->year)
            ->unique()->sortDesc()->all());
    }

    /**
     * Activities in the period, newest first (undated ones last).
     *
     * @return Collection<int, Activity>
     */
    public function activities(): Collection
    {
        return $this->activitiesCache ??= Activity::query()
            ->when($this->year !== null, fn (Builder $query) => $query->whereYear('starts_on', $this->year))
            ->withCount([
                'attendances as present_count' => fn (Builder $query) => $query->where('status', AttendanceStatus::Present),
                'attendances as excused_count' => fn (Builder $query) => $query->where('status', AttendanceStatus::Excused),
                'attendances as exempt_active_count' => fn (Builder $query) => $query->where('status', AttendanceStatus::Exempt)
                    ->whereRelation('member', 'status', MemberStatus::Active),
            ])
            ->orderByRaw('starts_on is null')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Turnout for each activity in the period.
     *
     * @return list<array{activity: Activity, present: int, excused: int, absent: int, expected: int, turnout: float|null}>
     */
    public function activityRows(): array
    {
        $active = Member::active()->count();

        return array_values($this->activities()->map(function (Activity $activity) use ($active): array {
            $present = (int) $activity->getAttribute('present_count');
            $excused = (int) $activity->getAttribute('excused_count');
            $expected = max(0, $active - (int) $activity->getAttribute('exempt_active_count'));

            return [
                'activity' => $activity,
                'present' => $present,
                'excused' => $excused,
                'absent' => max(0, $expected - $present - $excused),
                'expected' => $expected,
                'turnout' => $expected > 0 ? min(1.0, fdiv($present, $expected)) : null,
            ];
        })->all());
    }

    /**
     * Headline numbers for the period.
     *
     * @return array{activities: int, average_turnout: float|null, best: array{activity: Activity, turnout: float|null}|null}
     */
    public function summary(): array
    {
        $rows = collect($this->activityRows());
        $expected = $rows->sum('expected');
        $best = $rows->whereNotNull('turnout')->sortByDesc('turnout')->first();

        return [
            'activities' => $rows->count(),
            'average_turnout' => $expected > 0 ? min(1.0, fdiv($rows->sum('present'), $expected)) : null,
            'best' => $best === null ? null : ['activity' => $best['activity'], 'turnout' => $best['turnout']],
        ];
    }

    /**
     * Each member's attendance in the period, lowest or highest rate first. Members who weren't
     * required at any activity (no rate) always come last.
     *
     * @return list<array{member: Member, attended: int, excused: int, required: int, rate: float|null}>
     */
    public function memberRows(?MemberStatus $status = null, bool $lowestFirst = true): array
    {
        $activityIds = $this->activities()->pluck('id');

        $attendance = Attendance::query()
            ->whereIn('activity_id', $activityIds)
            ->get(['member_id', 'status'])
            ->groupBy('member_id');

        $members = Member::query()
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->with(['chapter', 'currentAssignments.position'])
            ->orderBy('full_name')
            ->get();

        $rows = $members->map(function (Member $member) use ($attendance, $activityIds): array {
            /** @var Collection<int, Attendance> $records */
            $records = $attendance->get($member->id, collect());
            $attended = $records->where('status', AttendanceStatus::Present)->count();
            $required = $activityIds->count() - $records->where('status', AttendanceStatus::Exempt)->count();

            return [
                'member' => $member,
                'attended' => $attended,
                'excused' => $records->where('status', AttendanceStatus::Excused)->count(),
                'required' => $required,
                'rate' => $required > 0 ? fdiv($attended, $required) : null,
            ];
        });

        return array_values($rows
            ->sortBy([
                fn (array $a, array $b): int => ($a['rate'] === null) <=> ($b['rate'] === null),
                fn (array $a, array $b): int => $lowestFirst ? $a['rate'] <=> $b['rate'] : $b['rate'] <=> $a['rate'],
                fn (array $a, array $b): int => $a['member']->full_name <=> $b['member']->full_name,
            ])
            ->all());
    }

    /**
     * Status of every member at every activity in the period, keyed by member id then activity id.
     * Missing entries mean absent.
     *
     * @return array<int, array<int, AttendanceStatus>>
     */
    public function grid(): array
    {
        $grid = [];

        Attendance::query()
            ->whereIn('activity_id', $this->activities()->pluck('id'))
            ->get(['member_id', 'activity_id', 'status'])
            ->each(function (Attendance $attendance) use (&$grid): void {
                $grid[$attendance->member_id][$attendance->activity_id] = $attendance->status;
            });

        return $grid;
    }
}
