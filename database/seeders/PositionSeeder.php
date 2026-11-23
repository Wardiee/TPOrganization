<?php

namespace Database\Seeders;

use App\Models\Position;

class PositionSeeder extends JsonSeeder
{
    public function run(): void
    {
        foreach ($this->data('positions.json') as $position) {
            Position::create([
                'code' => $position['code'],
                'title' => $position['title'],
                'branch' => $position['branch'],
                'sort' => $position['sort'],
            ]);
        }
    }
}
