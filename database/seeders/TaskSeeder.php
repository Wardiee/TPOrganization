<?php

namespace Database\Seeders;

use App\Models\Task;
use Carbon\CarbonImmutable;

class TaskSeeder extends JsonSeeder
{
    /**
     * Seed the founder's own (non-automatic) tasks. Automatic tasks come from tasks:sync.
     */
    public function run(): void
    {
        foreach ($this->data('tasks.json') as $record) {
            Task::create([
                'title' => $record['title'],
                'detail' => $record['detail'],
                'category' => $record['category'],
                'priority' => $record['priority'],
                'due_on' => $record['due_on'],
                'link_route' => $record['link_route'],
                'is_auto' => false,
                'completed_at' => $record['completed_on'] === null
                    ? null
                    : CarbonImmutable::parse($record['completed_on'])->endOfDay(),
            ]);
        }
    }
}
