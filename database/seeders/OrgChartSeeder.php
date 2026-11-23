<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\OrgChart;
use App\Models\Position;
use App\Models\PositionAssignment;

class OrgChartSeeder extends JsonSeeder
{
    /**
     * Seats whose current holder comes from the chart itself, because the members list has no
     * entry for them: the Founder (the admin, not a member) and Andrei's Co-Founder seat.
     */
    private const SEATS_HELD_TODAY_FROM_CHART = ['FOUNDER', 'CO-FOUNDER'];

    /**
     * Seed the published "Org Chart 2024" snapshot exactly as printed (names included), plus the
     * current FOUNDER and CO-FOUNDER assignments taken from it. Run after MemberSeeder.
     */
    public function run(): void
    {
        $chart = $this->data('org-chart-2024.json');
        $positionIds = Position::pluck('id', 'code');
        $memberIds = Member::pluck('id', 'full_name');

        $orgChart = OrgChart::create([
            'title' => $chart['title'],
            'published_on' => $chart['published_on'],
            'updated_on' => $chart['updated_on'],
        ]);

        foreach ($chart['seats'] as $seat) {
            $positionId = $this->idFor($positionIds, $seat['position'], 'position');
            $memberId = $seat['member'] === null ? null : $this->idFor($memberIds, $seat['member'], 'member');

            $orgChart->seats()->create([
                'position_id' => $positionId,
                'member_id' => $memberId,
                'name_as_listed' => $seat['name_as_listed'],
                'note' => $seat['note'] ?? null,
            ]);

            if (in_array($seat['position'], self::SEATS_HELD_TODAY_FROM_CHART, true)) {
                PositionAssignment::create([
                    'position_id' => $positionId,
                    'member_id' => $memberId,
                    'name_as_listed' => $seat['name_as_listed'],
                    'started_on' => $chart['published_on'],
                    'source' => $chart['title'],
                ]);
            }
        }
    }
}
