<?php

use App\Enums\Branch;
use App\Enums\HistoryKind;
use App\Models\HistoryEvent;
use App\Models\Member;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use App\Services\RecordChecks;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-22 15:00', 'Asia/Manila'));
    $this->actingAs(User::factory()->create());
});

function seat(string $code, Branch $branch = Branch::Trop): Position
{
    return Position::factory()->create(['code' => $code, 'title' => $code, 'branch' => $branch, 'sort' => (int) preg_replace('/\D/', '', $code)]);
}

test('reassigning a seat ends the old holder\'s time, opens a new one and keeps both', function () {
    $trop13 = seat('TROP-13');
    $jade = Member::factory()->inactive()->create(['full_name' => 'Jade Andrey Curambao']);
    $zien = Member::factory()->create(['full_name' => 'Zien V. Quilisadio']);
    $old = PositionAssignment::factory()->for($trop13)->for($jade)->create(['started_on' => '2024-05-02']);

    Livewire::test('assign-position')
        ->call('openFor', positionId: $trop13->id)
        ->set('memberId', (string) $zien->id)
        ->set('note', 'Replaces an inactive holder')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('position-assigned');

    expect($old->fresh()->ended_on->toDateString())->toBe('2026-11-22')
        ->and($trop13->assignments()->count())->toBe(2)
        ->and($trop13->fresh()->currentAssignment->member->is($zien))->toBeTrue()
        ->and(HistoryEvent::sole())
        ->title->toBe('Zien V. Quilisadio assigned to TROP-13')
        ->detail->toBe('Previously held by Jade Andrey Curambao. Replaces an inactive holder')
        ->kind->toBe(HistoryKind::Org);
});

test('a seat held by an inactive member can be left empty, which clears the VACANT issue', function () {
    $trop14 = seat('TROP-14');
    $charles = Member::factory()->inactive()->create(['status_changed_on' => '2026-03-02']);
    PositionAssignment::factory()->for($trop14)->for($charles)->create(['started_on' => '2024-05-02']);

    expect(collect(app(RecordChecks::class)->issues())->pluck('tag'))->toContain('VACANT');

    Livewire::test('assign-position')
        ->call('openFor', positionId: $trop14->id)
        ->set('memberId', 'empty')
        ->call('save')
        ->assertHasNoErrors();

    expect($trop14->fresh()->currentAssignment)->toBeNull()
        ->and($trop14->assignments()->count())->toBe(1)
        ->and(HistoryEvent::sole()->title)->toBe('TROP-14 left empty')
        ->and(collect(app(RecordChecks::class)->issues())->pluck('tag'))->not->toContain('VACANT');
});

test('moving a TROPS member to a TROP seat ends their TROPS seat in the same step', function () {
    $trops4 = seat('TROPS-4', Branch::Trops);
    $trop13 = seat('TROP-13');
    $clark = Member::factory()->create(['full_name' => 'Clark Jiro Marquez']);
    $trops4Assignment = PositionAssignment::factory()->for($trops4)->for($clark)->create(['started_on' => '2024-05-02']);

    Livewire::test('assign-position')
        ->call('openFor', memberId: $clark->id)
        ->set('positionId', $trop13->id)
        ->assertSee('Move from TROPS-4')
        ->call('save')
        ->assertHasNoErrors();

    expect($trops4Assignment->fresh()->ended_on->toDateString())->toBe('2026-11-22')
        ->and($clark->currentAssignments()->with('position')->get()->pluck('position.code')->all())->toBe(['TROP-13'])
        ->and(HistoryEvent::sole()->title)->toBe('Clark Jiro Marquez moved from TROPS-4 to TROP-13');
});

test('unticking "move" lets a member hold both seats', function () {
    $trops4 = seat('TROPS-4', Branch::Trops);
    $trop13 = seat('TROP-13');
    $clark = Member::factory()->create();
    PositionAssignment::factory()->for($trops4)->for($clark)->create(['started_on' => '2024-05-02']);

    Livewire::test('assign-position')
        ->call('openFor', positionId: $trop13->id)
        ->set('memberId', (string) $clark->id)
        ->set('moveFromOtherSeats', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($clark->currentAssignments()->count())->toBe(2);
});

test('the pop-up rejects changes that would break the history', function (array $input, string $errorField) {
    $trop9 = seat('TROP-9');
    $jeffrey = Member::factory()->create();
    PositionAssignment::factory()->for($trop9)->for($jeffrey)->create(['started_on' => '2024-05-02']);
    $other = Member::factory()->create();
    $floating = Member::factory()->floating()->create();

    $values = array_map(fn ($value) => match ($value) {
        'jeffrey' => (string) $jeffrey->id,
        'other' => (string) $other->id,
        'floating' => (string) $floating->id,
        default => $value,
    }, $input);

    Livewire::test('assign-position')
        ->call('openFor', positionId: $trop9->id)
        ->set($values)
        ->call('save')
        ->assertHasErrors([$errorField]);

    expect($trop9->assignments()->count())->toBe(1)
        ->and(HistoryEvent::count())->toBe(0);
})->with([
    'date before the current holder started' => [['memberId' => 'other', 'startedOn' => '2024-01-01'], 'startedOn'],
    'date in the future' => [['memberId' => 'other', 'startedOn' => '2026-12-01'], 'startedOn'],
    'the same holder again' => [['memberId' => 'jeffrey'], 'memberId'],
    'a member who is not active' => [['memberId' => 'floating'], 'memberId'],
]);

test('an empty seat needs someone to be chosen', function () {
    $trops10 = seat('TROPS-10', Branch::Trops);

    Livewire::test('assign-position')
        ->call('openFor', positionId: $trops10->id)
        ->call('save')
        ->assertHasErrors(['memberId' => 'required']);
});

test('the Founder seat cannot be reassigned from the app', function () {
    $founder = Position::factory()->create(['code' => 'FOUNDER', 'title' => 'Founder / Chairman', 'branch' => null, 'sort' => 0]);
    $member = Member::factory()->create();

    Livewire::test('assign-position')
        ->call('openFor', memberId: $member->id)
        ->set('positionId', $founder->id)
        ->call('save')
        ->assertHasErrors(['positionId']);
});

test('assigning from the profile gives a member with no position a seat', function () {
    $trops10 = seat('TROPS-10', Branch::Trops);
    $zien = Member::factory()->create();

    Livewire::test('assign-position')
        ->call('openFor', memberId: $zien->id)
        ->set('positionId', $trops10->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($zien->currentAssignments()->sole()->position->is($trops10))->toBeTrue();
});

describe('with the real records', function () {
    beforeEach(function () {
        config(['admin.email' => 'chairman@example.com', 'admin.password' => 'a-strong-password']);
        $this->seed(DatabaseSeeder::class);
    });

    test('Today flags inactive holders and the name mismatch, branch by branch', function () {
        $page = Livewire::test('pages::org-structure')->instance();
        $trop = $page->branchData(Branch::Trop);
        $trops = $page->branchData(Branch::Trops);
        $flagged = fn (array $branch) => collect([$branch['lead'], ...$branch['seats']])->whereNotNull('flag')
            ->mapWithKeys(fn ($seat) => [$seat['position']->code => $seat['flag']])->all();

        expect($flagged($trop))->toBe(['TROP-3' => 'mismatch', 'TROP-13' => 'inactive', 'TROP-14' => 'inactive'])
            ->and($flagged($trops))->toBe(['TROPS-14' => 'inactive', 'TROPS-15' => 'inactive'])
            ->and($trop['leadRole'])->toBe('CO-FOUNDER · TROP-1')
            ->and($trop['lead']['name'])->toBe('Andrei Klaus T. Serenio')
            ->and($trops['lead']['name'])->toBe('Jayron Perez')
            ->and($page->founder['name'])->toBe('Edward J. Dugan')
            ->and($page->unassigned->pluck('full_name')->all())->toBe(['Zien V. Quilisadio', 'Jhon Gabriel Suzon', 'Arnel Jay Adrian S. Cañas']);
    });

    test('the Org Chart 2024 view shows names exactly as printed and flags nothing', function () {
        $page = Livewire::test('pages::org-structure')->set('view', 'chart');
        $trop = $page->instance()->branchData(Branch::Trop);
        $trop3 = collect($trop['seats'])->first(fn ($seat) => $seat['position']->code === 'TROP-3');

        expect($trop3['name'])->toBe('Jan Griz C. Tulingin')
            ->and(collect($trop['seats'])->whereNotNull('flag'))->toBeEmpty();

        $page->assertSee('As published on May 2, 2024 (view only)');
    });

    test('history keeps every change, newest first', function () {
        $trop13 = Position::where('code', 'TROP-13')->sole();
        $zien = Member::where('full_name', 'Zien V. Quilisadio')->sole();

        Livewire::test('assign-position')
            ->call('openFor', positionId: $trop13->id)
            ->set('memberId', (string) $zien->id)
            ->call('save');

        $titles = Livewire::test('pages::org-structure')->instance()->history->pluck('title');

        expect($titles->first())->toBe('Zien V. Quilisadio assigned to TROP-13')
            ->and($titles)->toContain('Org Chart 2024 published', '6 members moved to Inactive', 'Tropang Potchi Brotherhood founded')
            ->and($trop13->assignments()->count())->toBe(2);
    });
});

test('reassigning a filled seat needs an explicit choice, so nothing is emptied by accident', function () {
    $trop9 = seat('TROP-9');
    PositionAssignment::factory()->for($trop9)->create(['started_on' => '2024-05-02']);

    Livewire::test('assign-position')
        ->call('openFor', positionId: $trop9->id)
        ->assertSet('memberId', '')
        ->call('save')
        ->assertHasErrors(['memberId' => 'required']);

    expect($trop9->fresh()->currentAssignment)->not->toBeNull();
});

test('"leave the seat empty" is not offered for a seat that is already empty', function () {
    $trops10 = seat('TROPS-10', Branch::Trops);

    Livewire::test('assign-position')
        ->call('openFor', positionId: $trops10->id)
        ->set('memberId', 'empty')
        ->call('save')
        ->assertHasErrors(['memberId']);
});

test('a new seat gets the next number in its branch and sorts after the others', function () {
    $trop15 = seat('TROP-15');
    seat('TROP-2');
    $trops1 = Position::factory()->create(['code' => 'TROPS-1', 'title' => 'TROPS-1', 'branch' => Branch::Trops, 'sort' => 16]);

    Livewire::test('pages::org-structure')
        ->call('addSeat', 'TROP')
        ->assertDispatched('open-assign-position');

    $added = Position::where('code', 'TROP-16')->sole();
    expect($added->branch)->toBe(Branch::Trop)
        ->and($added->sort)->toBeGreaterThan($trop15->sort)
        ->and(Position::ordered()->pluck('code')->all())->toBe(['TROP-2', 'TROP-15', 'TROP-16', 'TROPS-1'])
        ->and(HistoryEvent::sole()->title)->toBe('New seat added: TROP-16');
});

test('a new seat starts empty and can then be assigned', function () {
    seat('TROPS-15', Branch::Trops);
    $member = Member::factory()->create();

    Livewire::test('pages::org-structure')->call('addSeat', 'TROPS');
    $trops16 = Position::where('code', 'TROPS-16')->sole();

    Livewire::test('assign-position')
        ->call('openFor', positionId: $trops16->id)
        ->set('memberId', (string) $member->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($trops16->fresh()->currentAssignment->member->is($member))->toBeTrue();
});

test('seat holders and members without a seat show their profile photos', function () {
    $trop1 = seat('TROP-1');
    $trop2 = seat('TROP-2');
    $lead = Member::factory()->create(['photo_path' => 'member-photos/lead.jpg']);
    $holder = Member::factory()->create(['photo_path' => 'member-photos/holder.jpg']);
    $waiting = Member::factory()->create(['photo_path' => 'member-photos/waiting.jpg']);
    PositionAssignment::factory()->for($trop1)->for($lead)->create(['started_on' => '2024-05-02']);
    PositionAssignment::factory()->for($trop2)->for($holder)->create(['started_on' => '2024-05-02']);

    Livewire::test('pages::org-structure')
        ->assertSeeHtml($lead->photoUrl())
        ->assertSeeHtml($holder->photoUrl())
        ->assertSeeHtml($waiting->photoUrl());
});
