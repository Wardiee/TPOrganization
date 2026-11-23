<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\Activity;
use App\Models\Member;
use App\Models\OrgChart;
use App\Models\PositionAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Finds gaps and conflicts in the records for the dashboard's "Needs your attention" list.
 *
 * Checks, in display order: seats held by inactive members (VACANT), names that differ from the
 * latest org chart (CONFLICT), active members without a position (UNASSIGNED), active members
 * without a birth year (MISSING), and activities without a name or date (MISSING).
 */
class RecordChecks
{
    public function __construct(private NameMismatches $nameMismatches) {}

    /**
     * @return list<RecordIssue>
     */
    public function issues(): array
    {
        return array_values(array_filter([
            $this->seatsHeldByInactiveMembers(),
            ...$this->nameMismatches(),
            $this->activeMembersWithoutPosition(),
            $this->activeMembersWithoutBirthYear(),
            $this->activitiesWithoutNameOrDate(),
        ]));
    }

    private function seatsHeldByInactiveMembers(): ?RecordIssue
    {
        $assignments = PositionAssignment::current()
            ->whereRelation('member', 'status', MemberStatus::Inactive)
            ->with(['position', 'member'])
            ->get()
            ->sortBy(fn (PositionAssignment $assignment) => $assignment->position->sort)
            ->values();

        if ($assignments->isEmpty()) {
            return null;
        }

        $count = $assignments->count();
        $since = $assignments->map(fn (PositionAssignment $assignment) => $assignment->member?->status_changed_on)->filter()->max();

        return new RecordIssue(
            key: 'vacant',
            tag: 'VACANT',
            tone: RecordIssue::TONE_ALERT,
            title: $count === 1 ? '1 position is held by an inactive member' : "{$count} positions are held by inactive members",
            detail: $this->joinList($assignments->map(fn (PositionAssignment $assignment) => $assignment->position->code))
                .($since instanceof CarbonImmutable ? " since the {$since->format('M j, Y')} update." : '.'),
            actionLabel: 'Reassign',
            url: route('org-structure'),
        );
    }

    /**
     * @return list<RecordIssue>
     */
    private function nameMismatches(): array
    {
        $chartTitle = OrgChart::query()->latest('published_on')->latest('id')->value('title') ?? 'the org chart';

        return array_values(array_map(fn (array $mismatch) => new RecordIssue(
            key: "conflict:{$mismatch['member_id']}",
            tag: 'CONFLICT',
            tone: RecordIssue::TONE_ALERT,
            title: "Name mismatch for {$mismatch['position_code']}",
            detail: "Members list says “{$mismatch['member_name']}”; {$chartTitle} says “{$mismatch['name_as_listed']}”.",
            actionLabel: 'Review',
            url: route('members.show', $mismatch['member_id']),
        ), $this->nameMismatches->find()));
    }

    private function activeMembersWithoutPosition(): ?RecordIssue
    {
        $names = Member::active()->whereDoesntHave('currentAssignments')->orderBy('id')->pluck('full_name');

        if ($names->isEmpty()) {
            return null;
        }

        $count = $names->count();

        return new RecordIssue(
            key: 'unassigned',
            tag: 'UNASSIGNED',
            tone: RecordIssue::TONE_INFO,
            title: $count === 1 ? '1 active member has no position' : "{$count} active members have no position",
            detail: $names->implode(', ').'.',
            actionLabel: 'Assign',
            url: route('org-structure'),
        );
    }

    private function activeMembersWithoutBirthYear(): ?RecordIssue
    {
        $count = Member::active()->whereNull('birth_year')->count();

        if ($count === 0) {
            return null;
        }

        return new RecordIssue(
            key: 'missing-birth-year',
            tag: 'MISSING',
            tone: RecordIssue::TONE_MISSING,
            title: $count === 1 ? '1 active member is missing a birth year' : "{$count} active members are missing a birth year",
            detail: 'Ages can’t be calculated until the year is added.',
            actionLabel: 'Fill in',
            url: route('members.index', ['status' => 'active', 'missing' => 1]),
        );
    }

    private function activitiesWithoutNameOrDate(): ?RecordIssue
    {
        $activities = Activity::query()
            ->where(fn ($query) => $query->whereNull('name')->orWhere('name', '')->orWhereNull('starts_on'))
            ->orderBy('id')
            ->get();

        if ($activities->isEmpty()) {
            return null;
        }

        $count = $activities->count();
        $withoutName = $activities->filter(fn (Activity $activity) => blank($activity->name))->count();
        $withoutDate = $activities->whereNull('starts_on')->count();

        $title = match (true) {
            $withoutName === $count && $withoutDate < $count => $count === 1 ? '1 activity has no name' : "{$count} activities have no name",
            $withoutDate === $count && $withoutName === 0 => $count === 1 ? '1 activity has no date' : "{$count} activities have no date",
            default => $count === 1 ? '1 activity is missing a name or date' : "{$count} activities are missing a name or date",
        };

        $descriptions = $activities->map(fn (Activity $activity): string => match (true) {
            blank($activity->name) && $activity->starts_on !== null => $activity->date_label,
            blank($activity->name) => 'one with no date recorded',
            default => "“{$activity->name}” has no date",
        });

        return new RecordIssue(
            key: 'activities-missing-details',
            tag: 'MISSING',
            tone: RecordIssue::TONE_MISSING,
            title: $title,
            detail: $this->joinList($descriptions).'.',
            actionLabel: 'Edit',
            url: route('activities'),
        );
    }

    /**
     * "A", "A and B", "A, B and C".
     *
     * @param  Collection<int, string>  $items
     */
    private function joinList(Collection $items): string
    {
        return $items->count() <= 1 ? (string) $items->first() : $items->slice(0, -1)->implode(', ').' and '.$items->last();
    }
}
