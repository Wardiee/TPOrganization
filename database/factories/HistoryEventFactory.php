<?php

namespace Database\Factories;

use App\Enums\DatePrecision;
use App\Enums\HistoryKind;
use App\Models\HistoryEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistoryEvent>
 */
class HistoryEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'happened_on' => fake()->date(),
            'date_precision' => DatePrecision::Day,
            'title' => fake()->sentence(4),
            'kind' => HistoryKind::Org,
        ];
    }
}
