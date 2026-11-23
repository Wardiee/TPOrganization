<?php

use App\Enums\AttendanceStatus;
use App\Enums\MemberStatus;
use App\Models\Activity;
use App\Models\HistoryEvent;
use App\Models\Member;
use App\Models\OrgChart;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\Task;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    config(['admin.email' => 'chairman@example.com', 'admin.password' => 'a-strong-password']);
    $this->seed(DatabaseSeeder::class);
});

test('it seeds 42 members: 29 active, 7 floating, 6 inactive', function () {
    expect(Member::count())->toBe(42)
        ->and(Member::withStatus(MemberStatus::Active)->count())->toBe(29)
        ->and(Member::withStatus(MemberStatus::Floating)->count())->toBe(7)
        ->and(Member::withStatus(MemberStatus::Inactive)->count())->toBe(6);
});

test('attendance totals per activity match the founder\'s records', function () {
    $totals = Activity::query()
        ->withCount([
            'attendances as present' => fn ($query) => $query->where('status', AttendanceStatus::Present),
            'attendances as excused' => fn ($query) => $query->where('status', AttendanceStatus::Excused),
        ])
        ->orderBy('id')
        ->get()
        ->map(fn (Activity $activity) => [$activity->display_name, $activity->date_label, $activity->present, $activity->excused])
        ->all();

    expect($totals)->toBe([
        ['Leiana Outing', 'May 26, 2024', 3, 6],
        ['Graduation', 'May 29–30, 2024', 12, 0],
        ['Untitled activity', 'Oct 6, 2024', 17, 0],
        ['Untitled activity', 'Date not recorded', 15, 0],
        ['Wake', 'Dec 16, 2025', 15, 0],
        ['Year-End Bonding', 'Dec 30, 2025', 9, 0],
    ]);
});

test('TROP-9 is held by Jeffrey Oliver Gumobao', function () {
    $holder = Position::where('code', 'TROP-9')->sole()->currentAssignment->member;

    expect($holder->full_name)->toBe('Jeffrey Oliver Gumobao');
});

test('11 active members are missing a birth year or birthday', function () {
    $active = Member::active();

    expect((clone $active)->whereNull('birth_year')->count())->toBe(11)
        ->and((clone $active)->whereNull('birth_year')->whereNotNull('birth_month')->count())->toBe(8)
        ->and((clone $active)->whereNull('birth_month')->count())->toBe(3);
});

test('the Org Chart 2024 snapshot keeps names exactly as printed', function () {
    $chart = OrgChart::where('title', 'Org Chart 2024')->sole();
    $trop3 = $chart->seats()->whereRelation('position', 'code', 'TROP-3')->with('member')->sole();

    expect($chart->seats()->count())->toBe(32)
        ->and($trop3->name_as_listed)->toBe('Jan Griz C. Tulingin')
        ->and($trop3->member->full_name)->toBe('Jan Griz Cartagena');
});

test('seats held by inactive members stay open so they can be flagged', function () {
    $codes = PositionAssignment::current()
        ->whereRelation('member', 'status', MemberStatus::Inactive)
        ->with('position')
        ->get()
        ->pluck('position.code')
        ->sort()
        ->values()
        ->all();

    expect($codes)->toBe(['TROP-13', 'TROP-14', 'TROPS-14', 'TROPS-15']);
});

test('the founder and co-founder seats are filled from the chart', function () {
    $founder = Position::where('code', 'FOUNDER')->sole()->currentAssignment;
    $andrei = Member::where('full_name', 'Andrei Klaus T. Serenio')->sole();

    expect($founder->member_id)->toBeNull()
        ->and($founder->name_as_listed)->toBe('Edward J. Dugan')
        ->and($andrei->currentAssignments()->with('position')->get()->pluck('position.code')->sort()->values()->all())
        ->toBe(['CO-FOUNDER', 'TROP-1']);
});

test('3 active members have no position', function () {
    $names = Member::active()->whereDoesntHave('currentAssignments')->orderBy('id')->pluck('full_name')->all();

    expect($names)->toBe(['Zien V. Quilisadio', 'Jhon Gabriel Suzon', 'Arnel Jay Adrian S. Cañas']);
});

test('11 of 42 member profiles are complete', function () {
    $complete = Member::with('currentAssignments')->get()->filter(fn (Member $member) => $member->missing_fields === []);

    expect($complete)->toHaveCount(11);
});

test('inactive members have their status change on record', function () {
    $inactive = Member::withStatus(MemberStatus::Inactive)->with('statusChanges')->get();

    $changes = $inactive->map(fn (Member $member) => [
        $member->statusChanges->sole()->to_status,
        $member->statusChanges->sole()->changed_on->toDateString(),
    ])->unique()->values()->all();

    expect($inactive)->toHaveCount(6)
        ->and($changes)->toBe([[MemberStatus::Inactive, '2026-03-02']]);
});

test('history lists the newest change first', function () {
    expect(HistoryEvent::count())->toBe(6)
        ->and(HistoryEvent::latestFirst()->limit(2)->pluck('title')->all())
        ->toBe(['6 members moved to Inactive', 'Jhon Gabriel Suzon · birthday added']);
});

test('the founder\'s own tasks are seeded, one open and one completed', function () {
    $tasks = Task::orderBy('id')->get();

    expect($tasks)->toHaveCount(2)
        ->and($tasks->every(fn (Task $task) => ! $task->is_auto))->toBeTrue()
        ->and($tasks[0]->completed_at)->toBeNull()
        ->and($tasks[1]->completed_at)->not->toBeNull();
});
