<?php

namespace App\Livewire\Forms;

use App\Enums\Branch;
use App\Enums\DatePrecision;
use App\Enums\HistoryKind;
use App\Enums\MemberStatus;
use App\Models\HistoryEvent;
use App\Models\Member;
use App\Models\Position;
use App\Services\PositionAssignments;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The add / edit member form. Birthday is month + day + optional year.
 */
class MemberForm extends Form
{
    public ?Member $member = null;

    public string $full_name = '';

    public string $nickname = '';

    public ?int $birth_month = null;

    public ?int $birth_day = null;

    public ?int $birth_year = null;

    public string $grade_level = '';

    public ?int $chapter_id = null;

    public ?int $member_no = null;

    public string $status = 'active';

    public string $status_note = '';

    public string $joined_on = '';

    public string $remarks = '';

    /** New members only: '' (no position yet), 'TROP' (Main) or 'TROPS' (Ext). */
    public string $placement = '';

    /** New members only: an empty seat's id, or 'new' to add the next seat in the branch. */
    public string $seat = 'new';

    /**
     * When a branch is chosen, preselect its first empty seat (or a new one if none are empty).
     */
    public function updatedPlacement(): void
    {
        $this->seat = $this->placement === ''
            ? 'new'
            : (string) (self::emptySeats(Branch::from($this->placement))->pluck('id')->first() ?? 'new');
    }

    /**
     * Seats in a branch that nobody holds right now.
     *
     * @return Collection<int, Position>
     */
    public static function emptySeats(Branch $branch): Collection
    {
        return Position::query()->where('branch', $branch)->whereDoesntHave('assignments', fn ($query) => $query->whereNull('ended_on'))->ordered()->get();
    }

    public function setMember(Member $member): void
    {
        $this->member = $member;

        $this->full_name = $member->full_name;
        $this->nickname = (string) $member->nickname;
        $this->birth_month = $member->birth_month;
        $this->birth_day = $member->birth_day;
        $this->birth_year = $member->birth_year;
        $this->grade_level = (string) $member->grade_level;
        $this->chapter_id = $member->chapter_id;
        $this->member_no = $member->member_no;
        $this->status = $member->status->value;
        $this->joined_on = (string) $member->joined_on?->toDateString();
        $this->remarks = (string) $member->remarks;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:100'],
            'birth_month' => ['nullable', 'integer', 'between:1,12', 'required_with:birth_day,birth_year'],
            'birth_day' => ['nullable', 'integer', 'between:1,31', 'required_with:birth_month,birth_year', $this->dayExistsInMonth(...)],
            'birth_year' => ['nullable', 'integer', 'min:1900', 'max:'.CarbonImmutable::today()->year],
            'grade_level' => ['nullable', 'string', 'max:100'],
            'chapter_id' => ['nullable', 'integer', Rule::exists('chapters', 'id')],
            'member_no' => ['nullable', 'integer', 'min:1', Rule::unique('members', 'member_no')->ignore($this->member)],
            'status' => ['required', Rule::enum(MemberStatus::class)],
            'status_note' => ['nullable', 'string', 'max:500'],
            'joined_on' => ['nullable', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'placement' => ['nullable', Rule::enum(Branch::class), $this->onlyActiveCanBePlaced(...)],
            'seat' => [$this->seatIsEmptyInBranch(...)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'birth_month.required_with' => 'Choose the month too.',
            'birth_day.required_with' => 'Choose the day too.',
            'member_no.unique' => 'Another member already has this number.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'full_name' => 'full name',
            'birth_month' => 'month',
            'birth_day' => 'day',
            'birth_year' => 'birth year',
            'chapter_id' => 'chapter',
            'member_no' => 'member no.',
            'joined_on' => 'date joined',
        ];
    }

    /**
     * Reject days that don't exist in the month (Apr 31). Feb 29 is allowed unless the year is known and not a leap year.
     */
    private function dayExistsInMonth(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($this->birth_month) || ! is_numeric($value)) {
            return;
        }

        $year = is_numeric($this->birth_year) ? (int) $this->birth_year : 2000;

        if (! checkdate((int) $this->birth_month, (int) $value, $year)) {
            $fail('That day doesn’t exist in this month.');
        }
    }

    private function onlyActiveCanBePlaced(string $attribute, mixed $value, Closure $fail): void
    {
        if (filled($value) && $this->status !== MemberStatus::Active->value) {
            $fail('Only active members can be given a seat.');
        }
    }

    private function seatIsEmptyInBranch(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->placement === '' || $value === 'new') {
            return;
        }

        $branch = Branch::tryFrom($this->placement);

        if ($branch === null || ! self::emptySeats($branch)->contains('id', (int) $value)) {
            $fail('Choose an empty seat in this branch, or a new seat.');
        }
    }

    /**
     * Create a new member with their starting status, and note it in "Recent changes".
     * If a branch was chosen, give them a seat there (an empty one, or a newly added one).
     */
    public function store(): Member
    {
        $this->validate();

        return DB::transaction(function (): Member {
            $member = Member::create([...$this->attributes(), 'status' => MemberStatus::from($this->status)]);

            HistoryEvent::create([
                'happened_on' => CarbonImmutable::today(),
                'date_precision' => DatePrecision::Day,
                'title' => "{$member->full_name} added to members",
                'kind' => HistoryKind::Members,
            ]);

            if ($this->placement !== '') {
                $assignments = app(PositionAssignments::class);
                $on = $member->joined_on ?? CarbonImmutable::today();
                $position = $this->seat === 'new'
                    ? $assignments->addSeat(Branch::from($this->placement))
                    : Position::query()->findOrFail((int) $this->seat);

                $assignments->assign($position, $member, $on, 'Placed when added.', moveFromOtherSeats: false);
            }

            return $member;
        });
    }

    /**
     * Save changes. A changed status is recorded with its note and date.
     */
    public function update(): Member
    {
        $this->validate();

        $member = $this->member;

        DB::transaction(function () use ($member): void {
            $member->update($this->attributes());
            $member->changeStatus(MemberStatus::from($this->status), $this->noteOrNull());
        });

        $this->status_note = '';

        return $member;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(): array
    {
        return [
            'full_name' => trim($this->full_name),
            'nickname' => $this->blankToNull($this->nickname),
            'birth_month' => $this->birth_month,
            'birth_day' => $this->birth_day,
            'birth_year' => $this->birth_year,
            'grade_level' => $this->blankToNull($this->grade_level),
            'chapter_id' => $this->chapter_id,
            'member_no' => $this->member_no,
            'joined_on' => $this->blankToNull($this->joined_on),
            'remarks' => $this->blankToNull($this->remarks),
        ];
    }

    private function noteOrNull(): ?string
    {
        return $this->blankToNull($this->status_note);
    }

    private function blankToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
