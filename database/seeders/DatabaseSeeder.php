<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
    }
}
