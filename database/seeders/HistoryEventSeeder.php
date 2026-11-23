<?php

namespace Database\Seeders;

use App\Enums\DatePrecision;
use App\Models\HistoryEvent;

class HistoryEventSeeder extends JsonSeeder
{
    /**
     * Seed in file order, so events on the same day keep the order they are listed in.
     */
    public function run(): void
    {
        foreach ($this->data('history.json') as $event) {
            HistoryEvent::create([
                'happened_on' => $event['date'],
                'date_precision' => $event['date_precision'] ?? DatePrecision::Day,
                'title' => $event['title'],
                'detail' => $event['detail'] ?? null,
                'kind' => $event['kind'],
            ]);
        }
    }
}
