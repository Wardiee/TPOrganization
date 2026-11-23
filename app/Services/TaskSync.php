<?php

namespace App\Services;

use App\Enums\Branch;
use App\Enums\MemberStatus;
use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Models\Activity;
use App\Models\Member;
use App\Models\PositionAssignment;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the automatic tasks in step with the records (run by tasks:sync, daily at 06:00 Manila).
 *
 * Each automatic task has a stable source_key. Running the sync again updates the same tasks instead
 * of adding new ones. A task the admin completed is never reopened. When a problem goes away, its open
 * task is closed automatically (auto_closed), and it may reopen if the problem comes back.
 */
class TaskSync
{
    /** Birthday greeting tasks are created this many days ahead. */
    public const BIRTHDAY_DAYS_AHEAD = 30;

    public function __construct(
        private NameMismatches $nameMismatches,
        private UpcomingBirthdays $birthdays,
    ) {}

    /**
     * @return array{created: int, updated: int, reopened: int, closed: int}
     */
    public function sync(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $wanted = $this->wantedTasks($today);
        $stats = ['created' => 0, 'updated' => 0, 'reopened' => 0, 'closed' => 0];

        DB::transaction(function () use ($wanted, $today, &$stats): void {
            $existing = Task::query()->where('is_auto', true)->whereNotNull('source_key')->get()->keyBy('source_key');

            foreach ($wanted as $key => $attributes) {
                /** @var Task|null $task */
                $task = $existing->get($key);

                if ($task === null) {
                    Task::create([...$attributes, 'source_key' => $key, 'is_auto' => true]);
                    $stats['created']++;

                    continue;
                }

                if ($task->isDone() && ! $task->auto_closed) {
                    continue;
                }

                if ($task->auto_closed) {
                    $stats['reopened']++;
                } else {
                    $stats['updated']++;
                }

                $task->fill([...$attributes, 'completed_at' => null, 'auto_closed' => false])->save();
            }

            $existing
                ->reject(fn (Task $task, string $key) => array_key_exists($key, $wanted) || $task->isDone())
                ->each(function (Task $task) use ($today, &$stats): void {
                    $task->forceFill(['completed_at' => $today->setTimeFrom(CarbonImmutable::now()), 'auto_closed' => true])->save();
                    $stats['closed']++;
                });
        });

        return $stats;
    }

    /**
     * The automatic tasks the records call for right now, keyed by source_key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function wantedTasks(CarbonImmutable $today): array
    {
        return [
            ...$this->birthdayGreetings($today),
            ...$this->seatsHeldByInactiveMembers(),
            ...$this->nameConflicts(),
            ...$this->membersWithoutPosition(),
            ...$this->missingBirthYears(),
            ...$this->activitiesMissingDetails(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function birthdayGreetings(CarbonImmutable $today): array
    {
        $tasks = [];

        foreach ($this->birthdays->from($today, self::BIRTHDAY_DAYS_AHEAD) as $birthday) {
            /** @var Member $member */
            $member = $birthday['member'];

            $tasks["birthday:{$member->id}:{$birthday['date']->toDateString()}"] = [
                'title' => "Birthday greeting: {$member->full_name}",
                'detail' => collect([
                    $member->nickname ? "“{$member->nickname}”" : null,
                    $member->primaryPosition()?->code,
                    $birthday['turns'] === null ? 'birth year missing' : "turns {$birthday['turns']}",
                ])->filter()->implode(' · '),
                'category' => TaskCategory::Birthdays,
                'priority' => null,
                'due_on' => $birthday['date'],
                'link_route' => '/birthdays?month='.$birthday['date']->format('Y-m'),
            ];
        }

        return $tasks;
    }

    /**
     * One task per branch: "Reassign TROP-13 and TROP-14".
     *
     * @return array<string, array<string, mixed>>
     */
    private function seatsHeldByInactiveMembers(): array
    {
        return PositionAssignment::current()
            ->whereRelation('member', 'status', MemberStatus::Inactive)
            ->with(['position', 'member'])
            ->get()
            ->filter(fn (PositionAssignment $assignment) => $assignment->position->branch !== null)
            ->sortBy(fn (PositionAssignment $assignment) => $assignment->position->sort)
            ->groupBy(fn (PositionAssignment $assignment) => $assignment->position->branch->value)
            ->sortKeysUsing(fn (string $a, string $b) => ($a === Branch::Trop->value ? 0 : 1) <=> ($b === Branch::Trop->value ? 0 : 1))
            ->mapWithKeys(function (Collection $assignments, string $branch): array {
                $since = $assignments->map(fn (PositionAssignment $assignment) => $assignment->member?->status_changed_on)->filter()->max();

                return ["vacant:{$branch}" => [
                    'title' => 'Reassign '.$this->joinList($assignments->map(fn (PositionAssignment $assignment) => $assignment->position->code)),
                    'detail' => 'Held by '.$this->joinList($assignments->map(fn (PositionAssignment $assignment) => $assignment->member?->full_name))
                        .', inactive'.($since instanceof CarbonImmutable ? ' since '.$since->format('M j, Y') : '').'.',
                    'category' => TaskCategory::Structure,
                    'priority' => TaskPriority::High,
                    'due_on' => null,
                    'link_route' => 'org-structure',
                ]];
            })
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function nameConflicts(): array
    {
        $tasks = [];

        foreach ($this->nameMismatches->find() as $mismatch) {
            $tasks["conflict:{$mismatch['member_id']}"] = [
                'title' => "Confirm the correct name for {$mismatch['position_code']}",
                'detail' => "“{$mismatch['member_name']}” vs “{$mismatch['name_as_listed']}”.",
                'category' => TaskCategory::Records,
                'priority' => TaskPriority::Medium,
                'due_on' => null,
                'link_route' => '/members/'.$mismatch['member_id'],
            ];
        }

        return $tasks;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function membersWithoutPosition(): array
    {
        $names = Member::active()->whereDoesntHave('currentAssignments')->orderBy('id')->pluck('full_name');

        if ($names->isEmpty()) {
            return [];
        }

        return ['unassigned' => [
            'title' => $names->count() === 1 ? 'Assign a position to 1 active member' : "Assign positions to {$names->count()} active members",
            'detail' => $names->implode(', ').'.',
            'category' => TaskCategory::Structure,
            'priority' => TaskPriority::Medium,
            'due_on' => null,
            'link_route' => 'org-structure',
        ]];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function missingBirthYears(): array
    {
        $count = Member::active()->whereNull('birth_year')->count();

        if ($count === 0) {
            return [];
        }

        return ['missing-birth-year' => [
            'title' => $count === 1 ? 'Collect the birth year from 1 member' : "Collect birth years from {$count} members",
            'detail' => 'Needed to calculate ages correctly.',
            'category' => TaskCategory::Members,
            'priority' => TaskPriority::Medium,
            'due_on' => null,
            'link_route' => '/members?status=active&missing=1',
        ]];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function activitiesMissingDetails(): array
    {
        $activities = Activity::query()
            ->where(fn ($query) => $query->whereNull('name')->orWhere('name', '')->orWhereNull('starts_on'))
            ->orderBy('id')
            ->get();

        if ($activities->isEmpty()) {
            return [];
        }

        $count = $activities->count();
        $allUnnamed = $activities->every(fn (Activity $activity) => blank($activity->name));

        $title = match (true) {
            $allUnnamed && $count === 1 => 'Name the untitled activity',
            $allUnnamed => "Name the {$count} untitled activities",
            $count === 1 => 'Add the missing name or date for 1 activity',
            default => "Add the missing names and dates for {$count} activities",
        };

        $descriptions = $activities->map(fn (Activity $activity): string => match (true) {
            blank($activity->name) && $activity->starts_on !== null => $activity->date_label,
            blank($activity->name) => 'one with no date recorded',
            default => "“{$activity->name}” has no date",
        });

        return ['activities-missing-details' => [
            'title' => $title,
            'detail' => $this->joinList($descriptions, ', and ').'.',
            'category' => TaskCategory::Activities,
            'priority' => TaskPriority::Low,
            'due_on' => null,
            'link_route' => 'activities',
        ]];
    }

    /**
     * "A", "A and B", "A, B and C" (or a custom last separator, e.g. ", and ").
     *
     * @param  Collection<int, string>|Collection<int, string|null>  $items
     */
    private function joinList(Collection $items, string $lastSeparator = ' and '): string
    {
        $items = $items->filter()->values();

        return $items->count() <= 1 ? (string) $items->first() : $items->slice(0, -1)->implode(', ').$lastSeparator.$items->last();
    }
}
