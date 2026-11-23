<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Name comparisons for spotting real mismatches between the members list and the org chart.
 *
 * Shortened forms ("Axel N. Bartolome" vs "Axel Niza Bartolome", "Ricky S. Tilago Jr.") are
 * the same person; only a different last name counts as a mismatch.
 */
class PersonName
{
    private const SUFFIXES = ['jr', 'sr', 'ii', 'iii', 'iv'];

    /**
     * The last name, ignoring suffixes like Jr./Sr./II/III, lower-cased for comparison.
     */
    public static function lastName(string $fullName): string
    {
        $words = preg_split('/\s+/', trim($fullName)) ?: [];

        while ($words !== []) {
            $word = Str::lower(rtrim((string) end($words), '.,'));

            if (! in_array($word, self::SUFFIXES, true)) {
                return $word;
            }

            array_pop($words);
        }

        return '';
    }

    public static function sameLastName(string $first, string $second): bool
    {
        return self::lastName($first) === self::lastName($second);
    }
}
