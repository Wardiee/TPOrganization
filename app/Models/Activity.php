<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\DatePrecision;
use App\Enums\HistoryKind;
use App\Enums\MemberStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * An activity or event. Real records may have no name and/or no date.
 *
 * Deleting is a soft delete: the activity and its attendance are hidden but kept.
 *
 * @property int $id
 * @property string|null $name
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property string|null $location
 * @property string|null $notes
 * @property-read string $display_name
 * @property-read string $date_label
 */
#[Fillable(['name', 'starts_on', 'ends_on', 'location', 'notes'])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * Rows exist for present, excused and not-required members; no row means absent.
     *
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
     * Record one member's attendance. Absent is stored as no row.
     */
    public function recordAttendance(Member $member, AttendanceStatus $status): void
    {
        if ($status === AttendanceStatus::Absent) {
            $this->attendances()->whereBelongsTo($member)->delete();

            return;
        }

        $this->attendances()->updateOrCreate(['member_id' => $member->id], ['status' => $status]);
    }

    /**
     * Active members expected at this activity: all active members except those marked "not required".
     * Turnout is present divided by this.
     */
    public function expectedCount(): int
    {
        $exempt = $this->attendances()
            ->where('status', AttendanceStatus::Exempt)
            ->whereRelation('member', 'status', MemberStatus::Active)
            ->count();

        return Member::active()->count() - $exempt;
    }

    /**
     * Delete (soft) the activity and note it in "Recent changes".
     */
    public function deleteWithHistory(): void
    {
        DB::transaction(function (): void {
            HistoryEvent::create([
                'happened_on' => today(),
                'date_precision' => DatePrecision::Day,
                'title' => "Activity removed: {$this->display_name}",
                'detail' => $this->date_label,
                'kind' => HistoryKind::Activity,
            ]);

            $this->delete();
        });
    }

    /**
     * Accessor for $activity->display_name.
     */
    public function getDisplayNameAttribute(): string
    {
        return filled($this->name) ? $this->name : 'Untitled activity';
    }

    /**
     * "Dec 30, 2025", "May 29–30, 2024", "May 30 – Jun 2, 2024", or "Date not recorded".
     *
     * Accessor for $activity->date_label.
     */
    public function getDateLabelAttribute(): string
    {
        $start = $this->starts_on;
        $end = $this->ends_on;

        if ($start === null) {
            return 'Date not recorded';
        }

        if ($end === null || $end->isSameDay($start)) {
            return $start->format('M j, Y');
        }

        if ($start->isSameMonth($end)) {
            return $start->format('M j').'–'.$end->format('j, Y');
        }

        if ($start->isSameYear($end)) {
            return $start->format('M j').' – '.$end->format('M j, Y');
        }

        return $start->format('M j, Y').' – '.$end->format('M j, Y');
    }
}
