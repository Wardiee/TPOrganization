<?php

use App\Models\Chapter;
use App\Models\Member;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Names of the members currently listed by the directory component.
 *
 * @return list<string>
 */
function listedNames($component): array
{
    return $component->instance()->rows->pluck('full_name')->all();
}

test('search matches name, nickname and position', function (string $search, array $expected) {
    $jeffrey = Member::factory()->create(['full_name' => 'Jeffrey Oliver Gumobao', 'nickname' => 'Jepoy']);
    Member::factory()->create(['full_name' => 'Randave N. Cagabhion', 'nickname' => 'Dave']);
    PositionAssignment::factory()->for($jeffrey)->for(Position::factory()->state(['code' => 'TROP-9', 'title' => 'TROP-9']))->create();

    $component = Livewire::test('pages::members.index')->set('search', $search);

    expect(listedNames($component))->toBe($expected);
})->with([
    'by name' => ['gumobao', ['Jeffrey Oliver Gumobao']],
    'by nickname' => ['dave', ['Randave N. Cagabhion']],
    'by position' => ['trop-9', ['Jeffrey Oliver Gumobao']],
    'no match' => ['zzz', []],
]);

test('status chips filter the list and show counts for every status', function () {
    Member::factory()->create(['full_name' => 'Active One']);
    Member::factory()->floating()->create(['full_name' => 'Floating One']);
    Member::factory()->inactive()->create(['full_name' => 'Inactive One']);

    $component = Livewire::test('pages::members.index')->set('status', 'floating');

    expect(listedNames($component))->toBe(['Floating One'])
        ->and($component->instance()->statusCounts)->toBe(['all' => 3, 'active' => 1, 'floating' => 1, 'inactive' => 1]);
});

test('the chapter filter keeps only members of that chapter', function () {
    $villariez = Chapter::factory()->create(['name' => 'Villariez']);
    Member::factory()->for($villariez)->create(['full_name' => 'From Villariez']);
    Member::factory()->create(['full_name' => 'From Elsewhere']);

    $component = Livewire::test('pages::members.index')->set('chapter', 'Villariez');

    expect(listedNames($component))->toBe(['From Villariez']);
});

test('"missing info only" keeps members with gaps in their records', function () {
    $complete = Member::factory()->create(['full_name' => 'Complete Member']);
    PositionAssignment::factory()->for($complete)->create();
    Member::factory()->withoutBirthYear()->create(['full_name' => 'No Birth Year']);

    $component = Livewire::test('pages::members.index')->set('missingOnly', true);

    expect(listedNames($component))->toBe(['No Birth Year']);
});

test('active members come first, then floating, then inactive, each A to Z', function () {
    Member::factory()->inactive()->create(['full_name' => 'Aaron Inactive']);
    Member::factory()->floating()->create(['full_name' => 'Bea Floating']);
    Member::factory()->create(['full_name' => 'Zed Active']);
    Member::factory()->create(['full_name' => 'Ana Active']);

    expect(listedNames(Livewire::test('pages::members.index')))
        ->toBe(['Ana Active', 'Zed Active', 'Bea Floating', 'Aaron Inactive']);
});

test('with the real records, only TROP-3 is flagged as a name mismatch and its row says so', function () {
    config(['admin.email' => 'chairman@example.com', 'admin.password' => 'a-strong-password']);
    $this->seed(DatabaseSeeder::class);

    $component = Livewire::test('pages::members.index')->set('missingOnly', true);

    expect(array_keys($component->instance()->nameMismatches))
        ->toBe([Member::where('full_name', 'Jan Griz Cartagena')->value('id')])
        ->and(listedNames($component))->toContain('Jan Griz Cartagena');

    $component->set('search', 'Jan Griz')->assertSee('Name differs on org chart');
});

test('selecting a row shows that member in the side panel', function () {
    Member::factory()->create(['full_name' => 'Ana Active']);
    $bea = Member::factory()->create(['full_name' => 'Bea Active', 'remarks' => 'Needs more participation']);

    Livewire::test('pages::members.index')
        ->call('select', $bea->id)
        ->assertSet('selectedId', $bea->id)
        ->assertSee('Founder’s remark')
        ->assertSee('Needs more participation');
});
