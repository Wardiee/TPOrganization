<?php

namespace Database\Seeders;

use App\Models\Chapter;

class ChapterSeeder extends JsonSeeder
{
    public function run(): void
    {
        foreach ($this->data('chapters.json') as $name) {
            Chapter::create(['name' => $name]);
        }
    }
}
