<?php

use App\Models\Member;
use App\Services\UpcomingBirthdays;
use Carbon\CarbonImmutable;

/**
 * @return list<array{0: string, 1: string, 2: int, 3: int|null}>
 */
function upcoming(string $today, ?int $withinDays = null): array
{
    return app(UpcomingBirthdays::class)
        ->from(CarbonImmutable::parse($today, 'Asia/Manila'), $withinDays)
        ->map(fn (array $birthday) => [$birthday['member']->full_name, $birthday['date']->toDateString(), $birthday['days'], $birthday['turns']])
        ->all();
}

test('birthdays are listed soonest first, wrapping into next year', function () {
    Member::factory()->bornOn(2007, 12, 25)->create(['full_name' => 'John Ronryl Edig']);
    Member::factory()->bornOn(2008, 2, 19)->create(['full_name' => 'Aidreianne Feb T. Serenio']);
    Member::factory()->withoutBirthYear()->create(['full_name' => 'Christoff Liel Indab', 'birth_month' => 12, 'birth_day' => 14]);

    expect(upcoming('2026-11-22'))->toBe([
        ['Christoff Liel Indab', '2026-12-14', 22, null],
        ['John Ronryl Edig', '2026-12-25', 33, 19],
        ['Aidreianne Feb T. Serenio', '2027-02-19', 89, 19],
    ]);
});

test('the window keeps only birthdays within that many days, including today', function () {
    Member::factory()->bornOn(2006, 11, 22)->create(['full_name' => 'Today']);
    Member::factory()->bornOn(2006, 12, 22)->create(['full_name' => 'In 30 days']);
    Member::factory()->bornOn(2006, 12, 23)->create(['full_name' => 'In 31 days']);

    expect(array_column(upcoming('2026-11-22', withinDays: 30), 0))->toBe(['Today', 'In 30 days']);
});

test('a Feb 29 birthday is on Mar 1 in non-leap years and Feb 29 in leap years', function (string $today, string $expectedDate) {
    Member::factory()->bornOn(2008, 2, 29)->create();

    expect(upcoming($today)[0][1])->toBe($expectedDate);
})->with([
    'non-leap year' => ['2027-01-10', '2027-03-01'],
    'leap year' => ['2028-01-10', '2028-02-29'],
]);

test('only active members with a birthday are included', function () {
    Member::factory()->floating()->bornOn(2006, 12, 1)->create();
    Member::factory()->inactive()->bornOn(2006, 12, 1)->create();
    Member::factory()->withoutBirthday()->create();

    expect(upcoming('2026-11-22'))->toBe([]);
});
