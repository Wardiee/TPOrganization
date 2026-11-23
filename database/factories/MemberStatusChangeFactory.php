<?php

namespace Database\Factories;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberStatusChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberStatusChange>
 */
class MemberStatusChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'from_status' => MemberStatus::Active,
            'to_status' => MemberStatus::Inactive,
            'changed_on' => fake()->date(),
        ];
    }
}
