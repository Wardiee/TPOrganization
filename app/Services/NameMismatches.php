<?php

namespace App\Services;

use App\Models\OrgChart;
use App\Models\OrgChartSeat;
use App\Models\PositionAssignment;
use App\Support\PersonName;

/**
 * Members whose name differs from how the latest published org chart prints it for their seat.
 *
 * Only compared when the seat is still held by the same person the chart lists. A seat reassigned since
 * the chart was published is a change, not a name conflict. Only a different last name counts (see
 * PersonName), so "Axel N. Bartolome" is not flagged.
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

        $seats = $chart->seats()->whereNotNull('member_id')->get()->keyBy('position_id');

        $mismatches = [];

        PositionAssignment::current()
            ->whereNotNull('member_id')
            ->whereIn('position_id', $seats->keys())
            ->with(['member', 'position'])
            ->orderBy('id')
            ->get()
            ->each(function (PositionAssignment $assignment) use ($seats, &$mismatches): void {
                $member = $assignment->member;
                /** @var OrgChartSeat $seat */
                $seat = $seats->get($assignment->position_id);
                $listed = $seat->name_as_listed;

                if ($member === null || $seat->member_id !== $member->id || PersonName::sameLastName($member->full_name, $listed)) {
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
