<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Base for seeders that load the founder's exported records from database/seeders/data/*.json.
 */
abstract class JsonSeeder extends Seeder
{
    /**
     * @return array<int|string, mixed>
     */
    protected function data(string $file): array
    {
        $path = database_path("seeders/data/{$file}");

        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException("Seed data file not found or unreadable: {$file}");
        }

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Look up an id by name or code, failing loudly so a typo in the data never seeds a broken link.
     *
     * @param  Collection<array-key, int>  $ids
     */
    protected function idFor(Collection $ids, string $key, string $what): int
    {
        return $ids->get($key) ?? throw new RuntimeException("Unknown {$what} in seed data: {$key}");
    }
}
