<?php

use App\Models\Activity;

test('an activity without a name shows as untitled', function (?string $name, string $expected) {
    expect(Activity::factory()->make(['name' => $name])->display_name)->toBe($expected);
})->with([
    'named' => ['Wake', 'Wake'],
    'no name' => [null, 'Untitled activity'],
    'blank name' => ['', 'Untitled activity'],
]);

test('the date label covers single days, ranges and missing dates', function (?string $startsOn, ?string $endsOn, string $expected) {
    $activity = Activity::factory()->make(['starts_on' => $startsOn, 'ends_on' => $endsOn]);

    expect($activity->date_label)->toBe($expected);
})->with([
    'single day' => ['2025-12-30', null, 'Dec 30, 2025'],
    'same start and end' => ['2025-12-30', '2025-12-30', 'Dec 30, 2025'],
    'range in one month' => ['2024-05-29', '2024-05-30', 'May 29–30, 2024'],
    'range across months' => ['2024-05-30', '2024-06-02', 'May 30 – Jun 2, 2024'],
    'range across years' => ['2025-12-30', '2026-01-02', 'Dec 30, 2025 – Jan 2, 2026'],
    'no date' => [null, null, 'Date not recorded'],
]);
