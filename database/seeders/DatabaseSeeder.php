<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the admin account and the founder's real records from database/seeders/data.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            ChapterSeeder::class,
            PositionSeeder::class,
            FolderSeeder::class,
            HistoryEventSeeder::class,
            MemberSeeder::class,
            OrgChartSeeder::class,
            ActivitySeeder::class,
            TaskSeeder::class,
        ]);

        // Automatic tasks from the seeded records, as the daily schedule would create them.
        Artisan::call('tasks:sync');
    }
}
