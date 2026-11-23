<?php

use App\Enums\Branch;
use App\Enums\MemberStatus;
use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Models\Member;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\Task;
use App\Services\TaskSync;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-22 06:00', 'Asia/Manila'));
});

/**
 * @return list<array{0: string, 1: string|null, 2: string, 3: string|null, 4: string|null}>
 */
function autoTaskRows(): array
{
    return Task::query()->where('is_auto', true)->whereNull('completed_at')->orderBy('id')->get()
        ->map(fn (Task $task) => [$task->title, $task->detail, $task->category->value, $task->priority?->value, $task->due_on?->toDateString()])
        ->all();
}

test('with the real records, the seeded tasks match the design under the 30-day birthday rule', function () {
    config(['admin.email' => 'chairman@example.com', 'admin.password' => 'a-strong-password']);
    $this->seed(DatabaseSeeder::class);

    expect(autoTaskRows())->toBe([
        ['Birthday greeting: Christoff Liel Indab', '“Tope” · TROP-15 · birth year missing', 'Birthdays', null, '2026-12-14'],
        ['Birthday greeting: Edrian M. Cagabhion', '“Samboy” · TROPS-12 · birth year missing', 'Birthdays', null, '2026-12-18'],
        ['Reassign TROP-13 and TROP-14', 'Held by Jade Andrey Curambao and Charles Louis Indab, inactive since Mar 2, 2026.', 'Structure', 'High', null],
        ['Reassign TROPS-14 and TROPS-15', 'Held by Rudy G. Omogos and Vincent Harvey D. Labendia, inactive since Mar 2, 2026.', 'Structure', 'High', null],
        ['Confirm the correct name for TROP-3', '“Jan Griz Cartagena” vs “Jan Griz C. Tulingin”.', 'Records', 'Medium', null],
        ['Assign positions to 3 active members', 'Zien V. Quilisadio, Jhon Gabriel Suzon, Arnel Jay Adrian S. Cañas.', 'Structure', 'Medium', null],
        ['Collect birth years from 11 members', 'Needed to calculate ages correctly.', 'Members', 'Medium', null],
        ['Name the 2 untitled activities', 'Oct 6, 2024, and one with no date recorded.', 'Activities', 'Low', null],
    ])->and(Task::where('is_auto', false)->count())->toBe(2)
        ->and(Task::whereNull('completed_at')->count())->toBe(9);
});

test('running the sync again does not duplicate tasks', function () {
    Member::factory()->withoutBirthYear()->create();
    $sync = app(TaskSync::class);

    expect($sync->sync())->toMatchArray(['created' => 2, 'updated' => 0]);
    expect($sync->sync())->toMatchArray(['created' => 0, 'updated' => 2]);
    expect(Task::count())->toBe(2);
});

test('a task the admin completed is never reopened, even while the problem remains', function () {
    Member::factory()->withoutBirthYear()->create();
    app(TaskSync::class)->sync();

    $task = Task::where('source_key', 'missing-birth-year')->sole();
    $task->forceFill(['completed_at' => now()])->save();

    app(TaskSync::class)->sync();

    expect($task->fresh()->completed_at)->not->toBeNull()
        ->and($task->fresh()->auto_closed)->toBeFalse()
        ->and(Task::where('source_key', 'missing-birth-year')->count())->toBe(1);
});

test('a task closes itself when its problem is fixed and reopens if it comes back', function () {
    $trop13 = Position::factory()->create(['code' => 'TROP-13', 'title' => 'TROP-13']);
    $holder = Member::factory()->inactive()->create(['full_name' => 'Jade Andrey Curambao']);
    $assignment = PositionAssignment::factory()->for($trop13)->for($holder)->create();
    app(TaskSync::class)->sync();
    $task = Task::where('source_key', 'vacant:TROP')->sole();

    $assignment->update(['ended_on' => '2026-11-22']);
    expect(app(TaskSync::class)->sync()['closed'])->toBe(1)
        ->and($task->fresh()->completed_at)->not->toBeNull()
        ->and($task->fresh()->auto_closed)->toBeTrue();

    PositionAssignment::factory()->for($trop13)->for($holder)->create();
    expect(app(TaskSync::class)->sync()['reopened'])->toBe(1)
        ->and($task->fresh()->completed_at)->toBeNull();
});

test('birthday greetings are created 30 days ahead and close after the birthday', function () {
    $member = Member::factory()->bornOn(2007, 12, 22)->create(['full_name' => 'Thirty Days', 'nickname' => null]);
    Member::factory()->bornOn(2007, 12, 23)->create(['full_name' => 'Thirty One Days']);

    app(TaskSync::class)->sync(CarbonImmutable::parse('2026-11-22'));

    $greeting = Task::where('category', TaskCategory::Birthdays)->sole();
    expect($greeting->title)->toBe('Birthday greeting: Thirty Days')
        ->and($greeting->detail)->toBe('turns 19')
        ->and($greeting->source_key)->toBe("birthday:{$member->id}:2026-12-22")
        ->and($greeting->priority)->toBeNull();

    app(TaskSync::class)->sync(CarbonImmutable::parse('2026-12-23'));

    expect($greeting->fresh()->auto_closed)->toBeTrue();
});

test('the founder\'s own tasks are never touched by the sync', function () {
    $manual = Task::factory()->create(['title' => 'Book venue', 'is_auto' => false]);

    app(TaskSync::class)->sync();

    expect($manual->fresh()->completed_at)->toBeNull();
});

test('the command reports what it did and runs every day at 06:00 Manila time', function () {
    Member::factory()->withoutBirthYear()->create();

    $this->artisan('tasks:sync')
        ->expectsOutput('Tasks synced: 2 created, 0 updated, 0 reopened, 0 closed.')
        ->assertSuccessful();

    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'tasks:sync'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 6 * * *')
        ->and($event->timezone)->toBe('Asia/Manila');
});

test('only inactive holders count, not floating ones', function () {
    $trops4 = Position::factory()->create(['code' => 'TROPS-4', 'title' => 'TROPS-4', 'branch' => Branch::Trops]);
    PositionAssignment::factory()->for($trops4)->for(Member::factory()->state(['status' => MemberStatus::Floating]))->create();

    app(TaskSync::class)->sync();

    expect(Task::where('source_key', 'like', 'vacant:%')->count())->toBe(0)
        ->and(TaskPriority::High)->toBeInstanceOf(TaskPriority::class);
});
