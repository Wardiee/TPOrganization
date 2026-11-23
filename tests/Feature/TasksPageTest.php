<?php

use App\Enums\TaskCategory;
use App\Models\Member;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-22 15:00', 'Asia/Manila'));
    $this->actingAs(User::factory()->create());
});

test('ticking a task marks it done, and unticking reopens it', function () {
    $task = Task::factory()->create(['title' => 'Book venue']);

    $page = Livewire::test('pages::tasks')->call('toggle', $task->id);
    expect($task->fresh()->completed_at)->not->toBeNull();

    $page->call('toggle', $task->id);
    expect($task->fresh()->completed_at)->toBeNull();
});

test('a completed automatic task stays completed by the admin', function () {
    $task = Task::factory()->create(['is_auto' => true, 'source_key' => 'old-problem', 'auto_closed' => true, 'completed_at' => now()]);

    Livewire::test('pages::tasks')->call('toggle', $task->id)->call('toggle', $task->id);

    expect($task->fresh())
        ->completed_at->not->toBeNull()
        ->auto_closed->toBeFalse();
});

test('a task can be added with a category and due date', function () {
    Livewire::test('pages::tasks')
        ->set('newTitle', '  Book venue for next outing ')
        ->set('newCategory', 'Activities')
        ->set('newDue', '2026-12-01')
        ->call('addTask')
        ->assertHasNoErrors()
        ->assertSet('newTitle', '');

    expect(Task::sole())
        ->title->toBe('Book venue for next outing')
        ->category->toBe(TaskCategory::Activities)
        ->is_auto->toBeFalse()
        ->due_on->toDateString()->toBe('2026-12-01');
});

test('a task needs a title and a known category', function (array $input, string $field) {
    Livewire::test('pages::tasks')->set($input)->call('addTask')->assertHasErrors([$field]);

    expect(Task::count())->toBe(0);
})->with([
    'no title' => [['newTitle' => '  '], 'newTitle'],
    'unknown category' => [['newTitle' => 'Something', 'newCategory' => 'Chores'], 'newCategory'],
]);

test('tasks are grouped: coming up soonest first, then no due date, completed only when asked', function () {
    Task::factory()->create(['title' => 'Later', 'due_on' => '2026-12-20']);
    Task::factory()->create(['title' => 'Sooner', 'due_on' => '2026-12-01']);
    Task::factory()->create(['title' => 'Whenever', 'due_on' => null]);
    Task::factory()->create(['title' => 'Finished', 'completed_at' => now()]);

    $titles = fn ($page) => collect($page->instance()->groups)->mapWithKeys(fn ($group) => [$group['title'] => $group['items']->pluck('title')->all()])->all();

    $page = Livewire::test('pages::tasks');
    expect($titles($page))->toBe(['Coming up' => ['Sooner', 'Later'], 'No due date' => ['Whenever']]);

    $page->set('filter', 'done');
    expect($titles($page))->toBe(['Completed' => ['Finished']]);

    $page->set('filter', 'all');
    expect(array_keys($titles($page)))->toBe(['Coming up', 'No due date', 'Completed']);
});

test('your own tasks can be deleted, automatic ones cannot', function () {
    $manual = Task::factory()->create(['is_auto' => false]);
    $auto = Task::factory()->create(['is_auto' => true, 'source_key' => 'some-check']);

    Livewire::test('pages::tasks')->call('deleteTask', $manual->id);
    expect(Task::find($manual->id))->toBeNull();

    Livewire::test('pages::tasks')->call('deleteTask', $auto->id)->assertStatus(404);
    expect(Task::find($auto->id))->not->toBeNull();
});

test('opening Tasks brings the automatic tasks up to date', function () {
    Member::factory()->withoutBirthYear()->create();

    Livewire::test('pages::tasks')->assertSee('Collect the birth year from 1 member');
});
