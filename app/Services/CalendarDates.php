<?php

namespace App\Services;

use App\Enums\DatePrecision;
use App\Enums\HistoryKind;
use App\Enums\MemberStatus;
use App\Models\Activity;
use App\Models\HistoryEvent;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Str;

/**
 * What falls on each day of a month: birthdays, activities and org milestones.
 */
class CalendarDates
{
    public const KIND_BIRTHDAY = 'birthday';

    public const KIND_ACTIVITY = 'activity';

    public const KIND_ORG = 'org';

    public function __construct(private UpcomingBirthdays $birthdays) {}

    /**
     * Items for each day of the month, keyed by day number.
     *
     * Birthdays are active members only unless $includeNotActive; Feb 29 birthdays show on Mar 1 in
     * non-leap years. Activities spanning several days appear on each day.
     *
     * @return array<int, list<array{kind: string, label: string, title: string, detail: string, url: string|null}>>
     */
    public function forMonth(int $year, int $month, bool $includeNotActive = false): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth()->startOfDay();
        $days = [];

        $members = Member::query()
            ->whereNotNull('birth_month')
            ->whereNotNull('birth_day')
            ->when(! $includeNotActive, fn ($query) => $query->where('status', MemberStatus::Active))
            ->orderBy('full_name')
            ->get();

        foreach ($members as $member) {
            $date = $this->birthdays->inYear($member, $year);

            if ($date->month !== $month) {
                continue;
            }

            $turns = $member->birth_year === null ? null : $year - $member->birth_year;
            $shortName = $member->nickname ?: Str::before($member->full_name, ' ');

            $days[$date->day][] = [
                'kind' => self::KIND_BIRTHDAY,
                'label' => $shortName.($turns === null ? '' : " · turns {$turns}"),
                'title' => $member->full_name.($member->nickname ? " ({$member->nickname})" : ''),
                'detail' => 'Birthday · '.($turns === null ? 'birth year missing' : "turns {$turns}")
                    .($member->status === MemberStatus::Active ? '' : ' · '.$member->status->label()),
                'url' => route('members.show', $member),
            ];
        }

        $activities = Activity::query()
            ->whereNotNull('starts_on')
            ->where('starts_on', '<=', $end)
            ->where(fn ($query) => $query
                ->where('starts_on', '>=', $start)
                ->orWhere('ends_on', '>=', $start))
            ->orderBy('starts_on')
            ->get();

        foreach ($activities as $activity) {
            $last = $activity->ends_on ?? $activity->starts_on;

            foreach (CarbonPeriod::create($activity->starts_on->max($start), $last->min($end)) as $day) {
                $days[$day->day][] = [
                    'kind' => self::KIND_ACTIVITY,
                    'label' => $activity->display_name,
                    'title' => $activity->display_name,
                    'detail' => 'Activity · '.$activity->date_label,
                    'url' => route('activities', ['activity' => $activity->id]),
                ];
            }
        }

        $milestones = HistoryEvent::query()
            ->whereIn('kind', [HistoryKind::Org, HistoryKind::Members])
            ->where('date_precision', DatePrecision::Day)
            ->whereBetween('happened_on', [$start, $end])
            ->orderBy('happened_on')
            ->orderBy('id')
            ->get();

        foreach ($milestones as $event) {
            $days[$event->happened_on->day][] = [
                'kind' => self::KIND_ORG,
                'label' => $event->title,
                'title' => $event->title,
                'detail' => 'Org update'.($event->detail ? ' · '.$event->detail : ''),
                'url' => route('org-structure'),
            ];
        }

        ksort($days);

        return $days;
    }
}
