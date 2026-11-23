<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\OrgChart;
use App\Models\OrgChartSeat;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgChartSeat>
 */
class OrgChartSeatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'org_chart_id' => OrgChart::factory(),
            'position_id' => Position::factory(),
            'member_id' => Member::factory(),
            'name_as_listed' => fake()->name(),
        ];
    }
}
