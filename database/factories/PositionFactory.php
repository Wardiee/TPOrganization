<?php

namespace Database\Factories;

use App\Enums\Branch;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $code = 'TROP-'.fake()->unique()->numberBetween(1, 99),
            'title' => $code,
            'branch' => Branch::Trop,
            'sort' => fake()->numberBetween(10, 99),
        ];
    }
}
