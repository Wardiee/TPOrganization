<?php

use App\Enums\AttendanceStatus;
use App\Enums\HistoryKind;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\HistoryEvent;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * @return array<int, string> member id => status value
 */
function storedMarks(Activity $activity): array
{
    return $activity->attendances()->pluck('status', 'member_id')->map(fn (AttendanceStatus $status) => $status->value)->all();
}

test('each tap saves present, excused or absent, and absent removes the row', function () {
    $activity = Activity::factory()->create();
    $member = Member::factory()->create();
    $page = Livewire::test('pages::activities');

    $page->call('setStatus', $member->id, 'present');
    expect(storedMarks($activity))->toBe([$member->id => 'present']);

    $page->call('setStatus', $member->id, 'excused');
    expect(storedMarks($activity))->toBe([$member->id => 'excused'])
        ->and(Attendance::count())->toBe(1);

    $page->call('setStatus', $member->id, 'absent');
    expect(Attendance::count())->toBe(0);
});

test('totals count present, excused and absent, and turnout is present over active members', function () {
    Activity::factory()->create();
    [$first, $second, $third, $fourth] = Member::factory()->count(4)->create();

    $page = Livewire::test('pages::activities')
        ->call('setStatus', $first->id, 'present')
        ->call('setStatus', $second->id, 'present')
        ->call('setStatus', $third->id, 'excused');

    expect($page->instance()->totals)->toBe(['present' => 2, 'excused' => 1, 'absent' => 1, 'turnout' => 50]);
});

test('an unknown status is rejected', function () {
    $activity = Activity::factory()->create();
    $member = Member::factory()->create();

    Livewire::test('pages::activities')
        ->call('setStatus', $member->id, 'late')
        ->assertHasErrors(['status']);

    expect($activity->attendances()->count())->toBe(0);
});

test('"Mark all present" marks every active member and leaves others alone', function () {
    $activity = Activity::factory()->create();
    $active = Member::factory()->count(3)->create();
    $floating = Member::factory()->floating()->create();
    Attendance::factory()->for($activity)->for($active[0])->create(['status' => AttendanceStatus::Excused]);

    Livewire::test('pages::activities')->call('markAllPresent');

    expect(storedMarks($activity))->toEqualCanonicalizing($active->mapWithKeys(fn ($member) => [$member->id => 'present'])->all())
        ->and(storedMarks($activity))->not->toHaveKey($floating->id);
});

test('the list shows active members plus anyone already marked for the activity', function () {
    $activity = Activity::factory()->create();
    Member::factory()->create(['full_name' => 'Active Member']);
    $marked = Member::factory()->inactive()->create(['full_name' => 'Inactive With Mark']);
    Member::factory()->inactive()->create(['full_name' => 'Inactive Without Mark']);
    Attendance::factory()->for($activity)->for($marked)->create();

    $names = Livewire::test('pages::activities')->instance()->rows->pluck('full_name')->all();

    expect($names)->toBe(['Active Member', 'Inactive With Mark']);
});

test('the newest activity is selected by default and another can be chosen', function () {
    $older = Activity::factory()->create(['name' => 'Wake']);
    $newer = Activity::factory()->create(['name' => 'Year-End Bonding']);

    Livewire::test('pages::activities')
        ->assertSee('Year-End Bonding')
        ->tap(fn ($page) => expect($page->instance()->selected->is($newer))->toBeTrue())
        ->call('selectActivity', $older->id)
        ->tap(fn ($page) => expect($page->instance()->selected->is($older))->toBeTrue());
});

test('with the real records, Leiana Outing shows 3 present, 6 excused and the inactive members already marked', function () {
    config(['admin.email' => 'chairman@example.com', 'admin.password' => 'a-strong-password']);
    $this->seed(DatabaseSeeder::class);
    $leiana = Activity::where('name', 'Leiana Outing')->sole();

    $page = Livewire::test('pages::activities')->call('selectActivity', $leiana->id);

    expect($page->instance()->totals)->toBe(['present' => 3, 'excused' => 6, 'absent' => 23, 'turnout' => 10])
        ->and($page->instance()->rows)->toHaveCount(32)
        ->and($page->instance()->rows->first()->full_name)->toBe('Andrei Klaus T. Serenio');
});

test('a new activity can be added without a name or date', function () {
    Livewire::test('pages::activities')
        ->call('openCreate')
        ->assertSet('formOpen', true)
        ->call('saveActivity')
        ->assertHasNoErrors()
        ->assertSet('formOpen', false);

    $activity = Activity::sole();
    expect($activity->display_name)->toBe('Untitled activity')
        ->and($activity->date_label)->toBe('Date not recorded')
        ->and(HistoryEvent::sole())
        ->title->toBe('New activity: Untitled activity')
        ->kind->toBe(HistoryKind::Activity);
});

test('a new activity with a date range is saved and selected', function () {
    $page = Livewire::test('pages::activities')
        ->call('openCreate')
        ->set('form.name', 'Graduation')
        ->set('form.starts_on', '2024-05-29')
        ->set('form.ends_on', '2024-05-30')
        ->set('form.location', 'Pag-asa')
        ->call('saveActivity')
        ->assertHasNoErrors();

    $activity = Activity::sole();
    expect($activity->date_label)->toBe('May 29–30, 2024')
        ->and($activity->location)->toBe('Pag-asa')
        ->and($page->instance()->selected->is($activity))->toBeTrue();
});

test('activity dates must make sense', function (array $dates, string $errorField) {
    Livewire::test('pages::activities')
        ->call('openCreate')
        ->set($dates)
        ->call('saveActivity')
        ->assertHasErrors([$errorField]);

    expect(Activity::count())->toBe(0);
})->with([
    'end before start' => [['form.starts_on' => '2024-05-30', 'form.ends_on' => '2024-05-29'], 'form.ends_on'],
    'end without start' => [['form.ends_on' => '2024-05-30'], 'form.starts_on'],
]);

test('an untitled activity can be given a name later', function () {
    $activity = Activity::factory()->create(['name' => null, 'starts_on' => '2024-10-06']);

    Livewire::test('pages::activities')
        ->call('openEdit')
        ->assertSet('form.starts_on', '2024-10-06')
        ->set('form.name', 'October Meetup')
        ->call('saveActivity')
        ->assertHasNoErrors();

    expect($activity->fresh()->name)->toBe('October Meetup')
        ->and(HistoryEvent::count())->toBe(0);
});
