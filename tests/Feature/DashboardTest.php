<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the greeting follows the time of day in Manila', function (string $time, string $greeting) {
    $this->travelTo(CarbonImmutable::parse("2026-11-22 {$time}", 'Asia/Manila'));
    $this->actingAs(User::factory()->create());

    expect(Livewire::test('pages::dashboard')->instance()->greeting)->toBe($greeting);
})->with([
    ['08:00', 'Good morning, Chairman'],
    ['15:00', 'Good afternoon, Chairman'],
    ['19:30', 'Good evening, Chairman'],
]);

describe('with the real records on Nov 22, 2026', function () {
    beforeEach(function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-22 15:00', 'Asia/Manila'));
        config(['admin.email' => 'chairman@example.com', 'admin.password' => 'a-strong-password']);
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::sole());
    });

    test('the summary tiles show the real numbers', function () {
        $dashboard = Livewire::test('pages::dashboard')->instance();

        expect($dashboard->memberCounts)->toBe(['total' => 42, 'active' => 29, 'floating' => 7, 'inactive' => 6])
            ->and($dashboard->lastActivity->display_name)->toBe('Year-End Bonding')
            ->and($dashboard->lastActivity->present_count)->toBe(9)
            ->and($dashboard->birthdaysSoon->count())->toBe(2)
            ->and($dashboard->profiles)->toBe(['complete' => 11, 'total' => 42, 'percent' => 26]);
    });

    test('the page shows the headline figures', function () {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Sunday, November 22, 2026')
            ->assertSee('Good afternoon, Chairman')
            ->assertSee('Year-End Bonding · Dec 30, 2025')
            ->assertSee('Next: Christoff (Tope) · Dec 14')
            ->assertSee('11 complete (26%)')
            ->assertSee('Leiana Outing also had 6 marked excused.');
    });

    test('upcoming birthdays list the next four, beyond the 30-day window', function () {
        $dashboard = Livewire::test('pages::dashboard')->instance();

        $rows = $dashboard->nextBirthdays->map(fn (array $birthday) => [
            $birthday['member']->full_name,
            $dashboard->birthdayDetail($birthday),
            $dashboard->inDays($birthday['days']),
        ])->all();

        expect($rows)->toBe([
            ['Christoff Liel Indab', 'Tope · TROP-15 · birth year missing', 'in 22 days'],
            ['Edrian M. Cagabhion', 'Samboy · TROPS-12 · birth year missing', 'in 26 days'],
            ['John Ronryl Edig', 'Jan² · TROPS-2 · turns 19', 'in 33 days'],
            ['Jan Griz Cartagena', 'Kim² · TROP-3 · turns 16', 'in 34 days'],
        ]);
    });

    test('the attendance chart, chapters and recent changes match the records', function () {
        $dashboard = Livewire::test('pages::dashboard')->instance();

        expect($dashboard->chartActivities->pluck('present_count')->all())->toBe([3, 12, 17, 15, 15, 9])
            ->and($dashboard->chapters->map(fn ($chapter) => [$chapter->name, $chapter->members_count])->all())
            ->toBe([['Pag-asa', 27], ['Villariez', 9], ['Dalsa', 3], ['Poblacion', 2], ['Davao', 1]])
            ->and($dashboard->recentChanges->map(fn ($event) => [$event->title, $dashboard->historyDate($event)])->all())
            ->toBe([
                ['6 members moved to Inactive', 'Mar 2, 2026'],
                ['Jhon Gabriel Suzon · birthday added', 'Mar 2, 2026'],
                ['Org chart updated', 'Oct 5, 2025'],
                ['Members list updated', 'Oct 1, 2025'],
            ]);
    });
});
