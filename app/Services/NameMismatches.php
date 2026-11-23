<?php

namespace App\Services;

use App\Models\OrgChart;
use App\Models\OrgChartSeat;
use App\Models\PositionAssignment;
use App\Support\PersonName;

/**
 * Seats where the member's name differs from the name printed on the latest published org chart.
 *
 * Only a different last name counts (see PersonName), so "Axel N. Bartolome" is not flagged.
 */
class NameMismatches
{
    /**
     * Mismatches keyed by member id.
     *
     * @return array<int, array{member_id: int, member_name: string, position_code: string, name_as_listed: string}>
     */
    public function find(): array
    {
        $chart = OrgChart::query()->latest('published_on')->latest('id')->first();

        if ($chart === null) {
            return [];
        }

        $listedNames = $chart->seats()->get()->mapWithKeys(
            fn (OrgChartSeat $seat): array => [$seat->position_id => $seat->name_as_listed],
        );

        $mismatches = [];

        PositionAssignment::current()
            ->whereNotNull('member_id')
            ->whereIn('position_id', $listedNames->keys())
            ->with(['member', 'position'])
            ->orderBy('id')
            ->get()
            ->each(function (PositionAssignment $assignment) use ($listedNames, &$mismatches): void {
                $member = $assignment->member;
                $listed = $listedNames->get($assignment->position_id);

                if ($member === null || PersonName::sameLastName($member->full_name, $listed)) {
                    return;
                }

                $mismatches[$member->id] ??= [
                    'member_id' => $member->id,
                    'member_name' => $member->full_name,
                    'position_code' => $assignment->position->code,
                    'name_as_listed' => $listed,
                ];
            });

        return $mismatches;
    }
}
