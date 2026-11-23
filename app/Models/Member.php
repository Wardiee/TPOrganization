<?php

namespace App\Models;

use App\Enums\DatePrecision;
use App\Enums\HistoryKind;
use App\Enums\MemberStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A member of the brotherhood. Many are minors, so never expose or log these records.
 *
 * Birthdays are month + day + optional year because many members have no birth year
 * recorded. Age is always calculated, never stored.
 *
 * @property int $id
 * @property int|null $member_no
 * @property string $full_name
 * @property string|null $nickname
 * @property int|null $birth_month
 * @property int|null $birth_day
 * @property int|null $birth_year
 * @property string|null $grade_level
 * @property int|null $chapter_id
 * @property MemberStatus $status
 * @property CarbonImmutable|null $status_changed_on
 * @property string|null $remarks
 * @property CarbonImmutable|null $joined_on
 * @property string|null $private_notes
 * @property string|null $photo_path
 * @property-read int|null $age
 * @property-read string $initials
 * @property-read string|null $birthday_label
 * @property-read list<string> $missing_fields
 */
#[Fillable([
    'member_no', 'full_name', 'nickname', 'birth_month', 'birth_day', 'birth_year', 'grade_level',
    'chapter_id', 'status', 'status_changed_on', 'remarks', 'joined_on', 'private_notes', 'photo_path',
])]
#[Hidden(['private_notes'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'member_no' => 'integer',
            'birth_month' => 'integer',
            'birth_day' => 'integer',
            'birth_year' => 'integer',
            'status' => MemberStatus::class,
            'status_changed_on' => 'date',
            'joined_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Chapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * @return HasMany<MemberStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(MemberStatusChange::class);
    }

    /**
     * Every position this member has held, past and present.
     *
     * @return HasMany<PositionAssignment, $this>
     */
    public function positionAssignments(): HasMany
    {
        return $this->hasMany(PositionAssignment::class);
    }

    /**
     * Positions held now (not ended). A member can hold more than one, e.g. CO-FOUNDER and TROP-1.
     *
     * @return HasMany<PositionAssignment, $this>
     */
    public function currentAssignments(): HasMany
    {
        return $this->positionAssignments()->whereNull('ended_on');
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function withStatus(Builder $query, MemberStatus $status): void
    {
        $query->where('status', $status);
    }

    /**
     * @param  Builder<Member>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', MemberStatus::Active);
    }

    public function hasBirthday(): bool
    {
        return $this->birth_month !== null && $this->birth_day !== null;
    }

    /**
     * "Oct 19, 2006", "Apr 22" without a year, or null when there is no birthday.
     */
    public function shortBirthdayLabel(): ?string
    {
        if (! $this->hasBirthday()) {
            return null;
        }

        $label = CarbonImmutable::create(2000, $this->birth_month, $this->birth_day)->format('M j');

        return $this->birth_year === null ? $label : "{$label}, {$this->birth_year}";
    }

    /**
     * Positions held now, in org chart order. Needs currentAssignments.position loaded.
     *
     * @return Collection<int, Position>
     */
    public function currentPositions(): Collection
    {
        return $this->currentAssignments
            ->map(fn (PositionAssignment $assignment): Position => $assignment->position)
            ->sortBy('sort')
            ->values();
    }

    /**
     * The seat to show when there is room for one: a TROP/TROPS seat before Founder/Co-Founder.
     */
    public function primaryPosition(): ?Position
    {
        $positions = $this->currentPositions();

        return $positions->first(fn (Position $position): bool => $position->branch !== null) ?? $positions->first();
    }

    /**
     * Change the member's status and keep the history: a status change row plus a "Recent changes" entry.
     */
    public function changeStatus(MemberStatus $to, ?string $note = null, ?CarbonImmutable $on = null): void
    {
        $from = $this->status;

        if ($from === $to) {
            return;
        }

        $on ??= CarbonImmutable::today();

        DB::transaction(function () use ($from, $to, $note, $on): void {
            $this->forceFill(['status' => $to, 'status_changed_on' => $on])->save();

            $this->statusChanges()->create([
                'from_status' => $from,
                'to_status' => $to,
                'changed_on' => $on,
                'note' => $note,
            ]);

            HistoryEvent::create([
                'happened_on' => $on,
                'date_precision' => DatePrecision::Day,
                'title' => "{$this->full_name} moved to {$to->label()}",
                'detail' => $note,
                'kind' => HistoryKind::Members,
            ]);
        });
    }

    /**
     * Age in whole years today (Asia/Manila), or null when the birth year or birthday is unknown.
     *
     * A Feb 29 birthday counts as passed from Mar 1 in non-leap years.
     *
     * Accessor for $member->age.
     */
    public function getAgeAttribute(): ?int
    {
        if ($this->birth_year === null || ! $this->hasBirthday()) {
            return null;
        }

        $today = CarbonImmutable::today();
        $hadBirthdayThisYear = [$today->month, $today->day] >= [$this->birth_month, $this->birth_day];

        return $today->year - $this->birth_year - ($hadBirthdayThisYear ? 0 : 1);
    }

    /**
     * First letters of the first and last names, skipping initials and suffixes like "S." or "Jr.".
     *
     * Accessor for $member->initials.
     */
    public function getInitialsAttribute(): string
    {
        $words = array_values(array_filter(
            preg_split('/\s+/', trim($this->full_name)) ?: [],
            fn (string $word): bool => $word !== '' && ! str_ends_with($word, '.'),
        ));

        if ($words === []) {
            return '';
        }

        $first = Str::substr($words[0], 0, 1);
        $last = count($words) > 1 ? Str::substr($words[array_key_last($words)], 0, 1) : '';

        return Str::upper($first.$last);
    }

    /**
     * "October 19, 2006", or "April 22" when the year is unknown, or null when there is no birthday.
     *
     * Accessor for $member->birthday_label.
     */
    public function getBirthdayLabelAttribute(): ?string
    {
        if (! $this->hasBirthday()) {
            return null;
        }

        // A leap year, so Feb 29 is always a valid date.
        $label = CarbonImmutable::create(2000, $this->birth_month, $this->birth_day)->format('F j');

        return $this->birth_year === null ? $label : "{$label}, {$this->birth_year}";
    }

    /**
     * Profile fields that still need filling in, in display order.
     *
     * "Birthday" replaces "Birth year" when nothing is recorded. "Position" only counts for active
     * members. Eager-load currentAssignments when calling this on a list of members.
     *
     * Accessor for $member->missing_fields.
     *
     * @return list<string>
     */
    public function getMissingFieldsAttribute(): array
    {
        $missing = [];

        if (blank($this->nickname)) {
            $missing[] = 'Nickname';
        }

        if (! $this->hasBirthday()) {
            $missing[] = 'Birthday';
        } elseif ($this->birth_year === null) {
            $missing[] = 'Birth year';
        }

        if (blank($this->grade_level)) {
            $missing[] = 'Grade';
        }

        if ($this->status === MemberStatus::Active && $this->currentAssignments->isEmpty()) {
            $missing[] = 'Position';
        }

        return $missing;
    }
}
