<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Next birthdays of members, counted from a given day (Asia/Manila).
 *
 * A Feb 29 birthday falls on Mar 1 in non-leap years, matching how ages are calculated.
 */
class UpcomingBirthdays
{
    /**
     * Active members' next birthdays, soonest first. Members without a birthday are skipped.
     *
     * @return Collection<int, array{member: Member, date: CarbonImmutable, days: int, turns: int|null}>
     */
    public function from(CarbonImmutable $today, ?int $withinDays = null, MemberStatus $status = MemberStatus::Active): Collection
    {
        $today = $today->startOfDay();

        $birthdays = Member::query()
            ->withStatus($status)
            ->whereNotNull('birth_month')
            ->whereNotNull('birth_day')
            ->with('currentAssignments.position')
            ->get()
            ->map(function (Member $member) use ($today): array {
                $date = $this->occurrenceOnOrAfter($member, $today);

                return [
                    'member' => $member,
                    'date' => $date,
                    'days' => (int) $today->diffInDays($date),
                    'turns' => $member->birth_year === null ? null : $date->year - $member->birth_year,
                ];
            });

        if ($withinDays !== null) {
            $birthdays = $birthdays->filter(fn (array $birthday): bool => $birthday['days'] <= $withinDays);
        }

        return $birthdays
            ->sort(fn (array $a, array $b): int => ($a['days'] <=> $b['days']) ?: strcasecmp($a['member']->full_name, $b['member']->full_name))
            ->values();
    }

    /**
     * The member's birthday in the given year: Feb 29 becomes Mar 1 when the year has no leap day.
     */
    public function inYear(Member $member, int $year): CarbonImmutable
    {
        if ($member->birth_month === 2 && $member->birth_day === 29 && ! checkdate(2, 29, $year)) {
            return CarbonImmutable::create($year, 3, 1);
        }

        return CarbonImmutable::create($year, $member->birth_month, $member->birth_day);
    }

    private function occurrenceOnOrAfter(Member $member, CarbonImmutable $today): CarbonImmutable
    {
        $thisYear = $this->inYear($member, $today->year);

        return $thisYear->lt($today) ? $this->inYear($member, $today->year + 1) : $thisYear;
    }
}
