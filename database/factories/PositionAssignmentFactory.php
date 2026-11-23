<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Position;
use App\Models\PositionAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PositionAssignment>
 */
class PositionAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position_id' => Position::factory(),
            'member_id' => Member::factory(),
            'started_on' => fake()->date(),
            'ended_on' => null,
        ];
    }
}
