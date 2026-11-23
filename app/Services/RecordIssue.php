<?php

namespace App\Services;

/**
 * One problem found in the records, shown under "Needs your attention".
 *
 * The key is stable between runs (e.g. "vacant", "conflict:17"), so automatic tasks can be matched to it.
 */
final readonly class RecordIssue
{
    public const TONE_ALERT = 'alert';

    public const TONE_INFO = 'info';

    public const TONE_MISSING = 'missing';

    public function __construct(
        public string $key,
        public string $tag,
        public string $tone,
        public string $title,
        public string $detail,
        public string $actionLabel,
        public string $url,
    ) {}
}
