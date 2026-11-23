<?php

namespace App\Console\Commands;

use App\Services\TaskSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tasks:sync')]
#[Description('Create, update and close the automatic tasks from the current records and upcoming birthdays')]
class SyncTasks extends Command
{
    public function handle(TaskSync $taskSync): int
    {
        $stats = $taskSync->sync();

        $this->info(sprintf(
            'Tasks synced: %d created, %d updated, %d reopened, %d closed.',
            $stats['created'],
            $stats['updated'],
            $stats['reopened'],
            $stats['closed'],
        ));

        return self::SUCCESS;
    }
}
