<?php

use App\Models\Member;
use App\Models\PositionAssignment;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-22 15:00', 'Asia/Manila'));
});

test('age is calculated from the full birthday', function (int $month, int $day, int $expectedAge) {
    $member = Member::factory()->bornOn(2006, $month, $day)->make();

    expect($member->age)->toBe($expectedAge);
})->with([
    'birthday already passed this year' => [10, 19, 20],
    'birthday is today' => [11, 22, 20],
    'birthday still to come' => [12, 25, 19],
]);

test('age is unknown without a birth year or birthday', function () {
    expect(Member::factory()->withoutBirthYear()->make()->age)->toBeNull()
        ->and(Member::factory()->withoutBirthday()->make()->age)->toBeNull();
});

test('a Feb 29 birthday counts from Mar 1 in non-leap years', function (string $today, int $expectedAge) {
    $this->travelTo(CarbonImmutable::parse($today, 'Asia/Manila'));

    expect(Member::factory()->bornOn(2008, 2, 29)->make()->age)->toBe($expectedAge);
})->with([
    'Feb 28, 2027' => ['2027-02-28', 18],
    'Mar 1, 2027' => ['2027-03-01', 19],
    'Feb 29, 2028' => ['2028-02-29', 20],
]);

test('initials use the first and last name, skipping middle initials and suffixes', function (string $name, string $expected) {
    expect(Member::factory()->make(['full_name' => $name])->initials)->toBe($expected);
})->with([
    ['Jeffrey Oliver Gumobao', 'JG'],
    ['Ricky S. Tilago Jr.', 'RT'],
    ['Arnel Jay Adrian S. Cañas', 'AC'],
    ['Zien', 'Z'],
]);

test('the birthday label shows what is recorded', function (array $birthday, ?string $expected) {
    expect(Member::factory()->make($birthday)->birthday_label)->toBe($expected);
})->with([
    'full birthday' => [['birth_month' => 10, 'birth_day' => 19, 'birth_year' => 2006], 'October 19, 2006'],
    'no birth year' => [['birth_month' => 4, 'birth_day' => 22, 'birth_year' => null], 'April 22'],
    'leap day, no birth year' => [['birth_month' => 2, 'birth_day' => 29, 'birth_year' => null], 'February 29'],
    'nothing recorded' => [['birth_month' => null, 'birth_day' => null, 'birth_year' => null], null],
]);

test('missing fields lists what still needs filling in, in display order', function () {
    $member = Member::factory()->withoutBirthday()->create(['nickname' => null, 'grade_level' => null]);

    expect($member->missing_fields)->toBe(['Nickname', 'Birthday', 'Grade', 'Position']);
});

test('a member with month and day but no year is missing only the birth year', function () {
    $member = Member::factory()->withoutBirthYear()->create();
    PositionAssignment::factory()->for($member)->create();

    expect($member->missing_fields)->toBe(['Birth year']);
});

test('an ended position does not count as having a position', function () {
    $member = Member::factory()->create();
    PositionAssignment::factory()->for($member)->create(['ended_on' => '2026-03-02']);

    expect($member->missing_fields)->toBe(['Position']);
});

test('position only counts as missing for active members', function (string $state) {
    $member = Member::factory()->{$state}()->create();

    expect($member->missing_fields)->toBe([]);
})->with(['floating', 'inactive']);

test('private notes are never included when a member is serialized', function () {
    $member = Member::factory()->make(['private_notes' => 'Confidential note']);

    expect($member->toArray())->not->toHaveKey('private_notes');
});
