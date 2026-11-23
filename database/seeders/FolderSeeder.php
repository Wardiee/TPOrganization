<?php

namespace Database\Seeders;

use App\Models\Folder;

class FolderSeeder extends JsonSeeder
{
    public function run(): void
    {
        foreach ($this->data('folders.json') as $name) {
            Folder::create(['name' => $name]);
        }
    }
}
