<?php

use App\Enums\Branch;
use App\Enums\HistoryKind;
use App\Enums\MemberStatus;
use App\Models\Chapter;
use App\Models\HistoryEvent;
use App\Models\Member;
use App\Models\MemberStatusChange;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-22 15:00', 'Asia/Manila'));
    $this->actingAs(User::factory()->create());
});

test('a member can be added with only a full name', function () {
    Livewire::test('pages::members.create')
        ->set('form.full_name', '  Zien V. Quilisadio ')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('members.show', Member::sole()));

    $member = Member::sole();
    expect($member->full_name)->toBe('Zien V. Quilisadio')
        ->and($member->status)->toBe(MemberStatus::Active)
        ->and($member->nickname)->toBeNull()
        ->and(HistoryEvent::sole()->title)->toBe('Zien V. Quilisadio added to members');
});

test('a member can be added with a birthday that has no year', function () {
    $chapter = Chapter::factory()->create();

    Livewire::test('pages::members.create')
        ->set('form.full_name', 'Chrej Rigel Simbahon')
        ->set('form.birth_month', 4)
        ->set('form.birth_day', 22)
        ->set('form.chapter_id', $chapter->id)
        ->set('form.status', 'floating')
        ->call('save')
        ->assertHasNoErrors();

    expect(Member::sole())
        ->birth_month->toBe(4)
        ->birth_day->toBe(22)
        ->birth_year->toBeNull()
        ->chapter_id->toBe($chapter->id)
        ->status->toBe(MemberStatus::Floating);
});

test('the form rejects incomplete or impossible birthdays', function (array $birthday, string $errorField) {
    Livewire::test('pages::members.create')
        ->set('form.full_name', 'Someone')
        ->set($birthday)
        ->call('save')
        ->assertHasErrors([$errorField]);

    expect(Member::count())->toBe(0);
})->with([
    'April 31' => [['form.birth_month' => 4, 'form.birth_day' => 31], 'form.birth_day'],
    'Feb 29 in a non-leap year' => [['form.birth_month' => 2, 'form.birth_day' => 29, 'form.birth_year' => 2007], 'form.birth_day'],
    'day without month' => [['form.birth_day' => 12], 'form.birth_month'],
    'year without month and day' => [['form.birth_year' => 2008], 'form.birth_month'],
    'year in the future' => [['form.birth_month' => 1, 'form.birth_day' => 1, 'form.birth_year' => 2030], 'form.birth_year'],
]);

test('Feb 29 is accepted when the year is unknown or a leap year', function (?int $year) {
    Livewire::test('pages::members.create')
        ->set('form.full_name', 'Leap Day')
        ->set('form.birth_month', 2)
        ->set('form.birth_day', 29)
        ->set('form.birth_year', $year)
        ->call('save')
        ->assertHasNoErrors();
})->with([null, 2008]);

test('a full name is required', function () {
    Livewire::test('pages::members.create')
        ->set('form.full_name', '   ')
        ->call('save')
        ->assertHasErrors(['form.full_name' => 'required']);
});

test('member numbers must be unique', function () {
    Member::factory()->create(['member_no' => 10]);

    Livewire::test('pages::members.create')
        ->set('form.full_name', 'Someone')
        ->set('form.member_no', 10)
        ->call('save')
        ->assertHasErrors(['form.member_no' => 'unique']);
});

test('editing saves the changes and keeps the member\'s own number', function () {
    $member = Member::factory()->create(['member_no' => 10, 'nickname' => 'Jepoy']);

    Livewire::test('pages::members.edit', ['member' => $member])
        ->assertSet('form.nickname', 'Jepoy')
        ->set('form.nickname', '')
        ->set('form.grade_level', '2nd Year College')
        ->set('form.joined_on', '2021-06-12')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('members.show', $member));

    expect($member->fresh())
        ->nickname->toBeNull()
        ->grade_level->toBe('2nd Year College')
        ->member_no->toBe(10)
        ->joined_on->toDateString()->toBe('2021-06-12');
});

test('changing status records the change and a "Recent changes" entry', function () {
    $member = Member::factory()->create(['full_name' => 'Rudy G. Omogos']);

    Livewire::test('pages::members.edit', ['member' => $member])
        ->set('form.status', 'inactive')
        ->assertSee('Changing from Active to Inactive')
        ->set('form.status_note', 'Moved away')
        ->call('save')
        ->assertHasNoErrors();

    $change = MemberStatusChange::sole();
    expect($member->fresh())
        ->status->toBe(MemberStatus::Inactive)
        ->status_changed_on->toDateString()->toBe('2026-11-22')
        ->and($change->from_status)->toBe(MemberStatus::Active)
        ->and($change->to_status)->toBe(MemberStatus::Inactive)
        ->and($change->note)->toBe('Moved away')
        ->and(HistoryEvent::sole())
        ->title->toBe('Rudy G. Omogos moved to Inactive')
        ->kind->toBe(HistoryKind::Members);
});

test('saving without changing status records no status change', function () {
    $member = Member::factory()->floating()->create();

    Livewire::test('pages::members.edit', ['member' => $member])
        ->set('form.nickname', 'New nickname')
        ->call('save')
        ->assertHasNoErrors();

    expect(MemberStatusChange::count())->toBe(0)
        ->and(HistoryEvent::count())->toBe(0);
});

test('earlier status changes are kept when the status changes again', function () {
    $member = Member::factory()->create();
    $member->changeStatus(MemberStatus::Floating, on: CarbonImmutable::parse('2026-01-05'));

    Livewire::test('pages::members.edit', ['member' => $member->fresh()])
        ->set('form.status', 'active')
        ->call('save');

    expect(MemberStatusChange::orderBy('id')->get()->map(fn ($change) => [$change->from_status, $change->to_status])->all())
        ->toBe([
            [MemberStatus::Active, MemberStatus::Floating],
            [MemberStatus::Floating, MemberStatus::Active],
        ]);
});

test('a birthday can be cleared by choosing the empty options', function () {
    $member = Member::factory()->bornOn(2008, 2, 19)->create();

    Livewire::test('pages::members.edit', ['member' => $member])
        ->set('form.birth_month', '')
        ->set('form.birth_day', '')
        ->set('form.birth_year', '')
        ->set('form.member_no', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($member->fresh())
        ->birth_month->toBeNull()
        ->birth_day->toBeNull()
        ->birth_year->toBeNull();
});

test('a new member can be placed in a new seat in the chosen branch', function () {
    $trops15 = Position::factory()->create(['code' => 'TROPS-15', 'title' => 'TROPS-15', 'branch' => Branch::Trops, 'sort' => 45]);
    PositionAssignment::factory()->for($trops15)->create();

    Livewire::test('pages::members.create')
        ->set('form.full_name', 'New Recruit')
        ->set('form.placement', 'TROPS')
        ->assertSet('form.seat', 'new')
        ->call('save')
        ->assertHasNoErrors();

    $member = Member::where('full_name', 'New Recruit')->sole();
    $assignment = $member->currentAssignments()->with('position')->sole();

    expect($assignment->position->code)->toBe('TROPS-16')
        ->and($assignment->started_on->toDateString())->toBe('2026-11-22')
        ->and(HistoryEvent::orderBy('id')->pluck('title')->all())->toBe([
            'New Recruit added to members',
            'New seat added: TROPS-16',
            'New Recruit assigned to TROPS-16',
        ]);
});

test('choosing a branch preselects its first empty seat, starting from the date joined', function () {
    $filled = Position::factory()->create(['code' => 'TROP-12', 'title' => 'TROP-12', 'branch' => Branch::Trop, 'sort' => 22]);
    PositionAssignment::factory()->for($filled)->create();
    $empty = Position::factory()->create(['code' => 'TROP-13', 'title' => 'TROP-13', 'branch' => Branch::Trop, 'sort' => 23]);

    Livewire::test('pages::members.create')
        ->set('form.full_name', 'Long-term Member')
        ->set('form.joined_on', '2021-06-12')
        ->set('form.placement', 'TROP')
        ->assertSet('form.seat', (string) $empty->id)
        ->call('save')
        ->assertHasNoErrors();

    $assignment = Member::where('full_name', 'Long-term Member')->sole()->currentAssignments()->sole();
    expect($assignment->position_id)->toBe($empty->id)
        ->and($assignment->started_on->toDateString())->toBe('2021-06-12');
});

test('a new member is only placed when the choice makes sense', function (array $input, string $errorField) {
    $filled = Position::factory()->create(['code' => 'TROP-9', 'title' => 'TROP-9', 'branch' => Branch::Trop, 'sort' => 19]);
    PositionAssignment::factory()->for($filled)->create();
    $tropsSeat = Position::factory()->create(['code' => 'TROPS-10', 'title' => 'TROPS-10', 'branch' => Branch::Trops, 'sort' => 40]);

    $values = array_map(fn ($value) => match ($value) {
        'filled' => (string) $filled->id,
        'trops' => (string) $tropsSeat->id,
        default => $value,
    }, $input);

    Livewire::test('pages::members.create')
        ->set('form.full_name', 'Someone')
        ->set($values)
        ->call('save')
        ->assertHasErrors([$errorField]);

    expect(Member::count())->toBe(1);
})->with([
    'a floating member' => [['form.status' => 'floating', 'form.placement' => 'TROP'], 'form.placement'],
    'a seat someone holds' => [['form.placement' => 'TROP', 'form.seat' => 'filled'], 'form.seat'],
    'a seat from the other branch' => [['form.placement' => 'TROP', 'form.seat' => 'trops'], 'form.seat'],
]);
