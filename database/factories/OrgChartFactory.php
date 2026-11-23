<?php

namespace Database\Factories;

use App\Models\OrgChart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgChart>
 */
class OrgChartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Org Chart '.fake()->year(),
            'published_on' => fake()->date(),
        ];
    }
}
