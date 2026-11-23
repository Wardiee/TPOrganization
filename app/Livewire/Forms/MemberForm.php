<?php

namespace App\Livewire\Forms;

use App\Enums\DatePrecision;
use App\Enums\HistoryKind;
use App\Enums\MemberStatus;
use App\Models\HistoryEvent;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Closure;
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

    /**
     * Create a new member with their starting status, and note it in "Recent changes".
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
