<?php

use App\Enums\DatePrecision;
use App\Enums\HistoryKind;
use App\Models\Activity;
use App\Models\HistoryEvent;
use App\Models\Member;
use App\Models\User;
use App\Services\CalendarDates;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-22 15:00', 'Asia/Manila'));
    $this->actingAs(User::factory()->create());
});

/**
 * @return array<int, list<string>> day => item labels
 */
function labelsByDay(int $year, int $month, bool $includeNotActive = false): array
{
    return array_map(
        fn (array $items) => array_column($items, 'label'),
        app(CalendarDates::class)->forMonth($year, $month, $includeNotActive),
    );
}

test('with the real records, the next 60 days lists active members\' birthdays soonest first', function () {
    config(['admin.email' => 'chairman@example.com', 'admin.password' => 'a-strong-password']);
    $this->seed(DatabaseSeeder::class);

    $page = Livewire::test('pages::birthdays')->instance();
    $rows = $page->upcoming->map(fn (array $birthday) => [
        $birthday['member']->full_name,
        $birthday['date']->format('M j'),
        $page->birthdayDetail($birthday),
        $page->inDays($birthday['days']),
    ])->all();

    expect($rows)->toBe([
        ['Christoff Liel Indab', 'Dec 14', 'Tope · birth year missing', 'in 22 days'],
        ['Edrian M. Cagabhion', 'Dec 18', 'Samboy · birth year missing', 'in 26 days'],
        ['John Ronryl Edig', 'Dec 25', 'Jan² · turns 19', 'in 33 days'],
        ['Jan Griz Cartagena', 'Dec 26', 'Kim² · turns 16', 'in 34 days'],
        ['Arnel Jay Adrian S. Cañas', 'Jan 3', 'Adrian · turns 13', 'in 42 days'],
        ['John Michael Balones', 'Jan 21', 'Soy² · birth year missing', 'in 60 days'],
    ]);
});

test('the calendar shows birthdays, activities on every day they run, and org updates', function () {
    Member::factory()->bornOn(2007, 12, 25)->create(['full_name' => 'John Ronryl Edig', 'nickname' => 'Jan²']);
    Member::factory()->withoutBirthYear()->create(['full_name' => 'Christoff Liel Indab', 'nickname' => 'Tope', 'birth_month' => 12, 'birth_day' => 14]);
    Activity::factory()->create(['name' => 'Year-End Camp', 'starts_on' => '2026-12-30', 'ends_on' => '2027-01-02']);
    HistoryEvent::factory()->create(['happened_on' => '2026-12-05', 'title' => 'Org chart updated', 'kind' => HistoryKind::Org]);
    HistoryEvent::factory()->create(['happened_on' => '2026-12-01', 'date_precision' => DatePrecision::Year, 'title' => 'Year-only event', 'kind' => HistoryKind::Org]);

    expect(labelsByDay(2026, 12))->toBe([
        5 => ['Org chart updated'],
        14 => ['Tope'],
        25 => ['Jan² · turns 19'],
        30 => ['Year-End Camp'],
        31 => ['Year-End Camp'],
    ])->and(labelsByDay(2027, 1))->toBe([
        1 => ['Year-End Camp'],
        2 => ['Year-End Camp'],
    ]);
});

test('a Feb 29 birthday shows on Mar 1 in non-leap years', function () {
    Member::factory()->bornOn(2008, 2, 29)->create(['nickname' => 'Leap']);

    expect(labelsByDay(2027, 2))->toBe([])
        ->and(labelsByDay(2027, 3))->toBe([1 => ['Leap · turns 19']])
        ->and(labelsByDay(2028, 2))->toBe([29 => ['Leap · turns 20']]);
});

test('floating and inactive members only appear when included', function () {
    Member::factory()->inactive()->bornOn(2005, 10, 29)->create(['nickname' => 'Charles']);
    Member::factory()->floating()->bornOn(2009, 10, 3)->create(['nickname' => 'Ruben']);

    expect(labelsByDay(2026, 10))->toBe([])
        ->and(labelsByDay(2026, 10, includeNotActive: true))->toBe([3 => ['Ruben · turns 17'], 29 => ['Charles · turns 21']]);

    Livewire::test('pages::birthdays')
        ->set('month', '2026-10')
        ->set('includeNotActive', true)
        ->assertSee('Charles · turns 21');
});

test('month navigation moves between months and Today comes back', function () {
    Livewire::test('pages::birthdays')
        ->assertSet('month', '2026-11')
        ->assertSet('selectedDay', 22)
        ->call('nextMonth')
        ->call('nextMonth')
        ->assertSet('month', '2027-01')
        ->assertSet('selectedDay', 0)
        ->call('previousMonth')
        ->assertSet('month', '2026-12')
        ->call('selectDay', 14)
        ->assertSet('selectedDay', 14)
        ->call('goToToday')
        ->assertSet('month', '2026-11')
        ->assertSet('selectedDay', 22);
});

test('an invalid month in the address falls back to this month', function () {
    $this->get(route('birthdays', ['month' => '2026-13']))->assertOk()->assertSee('November 2026');
});
