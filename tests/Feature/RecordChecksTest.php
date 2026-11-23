<?php

use App\Models\Activity;
use App\Models\Member;
use App\Models\PositionAssignment;
use App\Services\RecordChecks;
use App\Services\RecordIssue;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

/**
 * @return list<array{0: string, 1: string, 2: string, 3: string}>
 */
function issueRows(): array
{
    return array_map(
        fn (RecordIssue $issue) => [$issue->tag, $issue->title, $issue->detail, $issue->actionLabel],
        app(RecordChecks::class)->issues(),
    );
}

test('with the real records, the dashboard shows exactly the issues in the design', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-22 15:00', 'Asia/Manila'));
    config(['admin.email' => 'chairman@example.com', 'admin.password' => 'a-strong-password']);
    $this->seed(DatabaseSeeder::class);

    expect(issueRows())->toBe([
        ['VACANT', '4 positions are held by inactive members', 'TROP-13, TROP-14, TROPS-14 and TROPS-15 since the Mar 2, 2026 update.', 'Reassign'],
        ['CONFLICT', 'Name mismatch for TROP-3', 'Members list says “Jan Griz Cartagena”; Org Chart 2024 says “Jan Griz C. Tulingin”.', 'Review'],
        ['UNASSIGNED', '3 active members have no position', 'Zien V. Quilisadio, Jhon Gabriel Suzon, Arnel Jay Adrian S. Cañas.', 'Assign'],
        ['MISSING', '11 active members are missing a birth year', 'Ages can’t be calculated until the year is added.', 'Fill in'],
        ['MISSING', '2 activities have no name', 'Oct 6, 2024 and one with no date recorded.', 'Edit'],
    ]);
});

test('clean records produce no issues', function () {
    $member = Member::factory()->create();
    PositionAssignment::factory()->for($member)->create();
    Activity::factory()->create(['name' => 'Wake', 'starts_on' => '2025-12-16']);

    expect(app(RecordChecks::class)->issues())->toBe([]);
});

test('a single problem is described in the singular', function () {
    $holder = Member::factory()->inactive()->create(['status_changed_on' => '2026-03-02']);
    PositionAssignment::factory()->for($holder)->create();
    Member::factory()->withoutBirthYear()->create();
    Activity::factory()->create(['name' => 'Outing', 'starts_on' => null]);

    expect(array_column(issueRows(), 1))->toBe([
        '1 position is held by an inactive member',
        '1 active member has no position',
        '1 active member is missing a birth year',
        '1 activity has no date',
    ]);
});

test('an ended assignment of an inactive member is not flagged', function () {
    $former = Member::factory()->inactive()->create();
    PositionAssignment::factory()->for($former)->create(['ended_on' => '2026-03-02']);

    expect(array_column(issueRows(), 0))->not->toContain('VACANT');
});
