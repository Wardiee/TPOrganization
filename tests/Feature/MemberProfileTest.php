<?php

use App\Enums\AttendanceStatus;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-22 15:00', 'Asia/Manila'));
    $this->actingAs(User::factory()->create());
});

test('the profile shows the member\'s details, position and calculated age', function () {
    $member = Member::factory()->bornOn(2006, 10, 19)->create([
        'full_name' => 'Jeffrey Oliver Gumobao',
        'nickname' => 'Jepoy',
        'member_no' => 10,
    ]);
    PositionAssignment::factory()->for($member)
        ->for(Position::factory()->state(['code' => 'TROP-9', 'title' => 'TROP-9']))
        ->create(['started_on' => '2024-05-02', 'source' => 'Org Chart 2024']);

    $this->get(route('members.show', $member))
        ->assertOk()
        ->assertSee('“Jepoy”', false)
        ->assertSee('Member no. 10')
        ->assertSee('TROP-9 · Main')
        ->assertSee('TROP-9 (Main branch)')
        ->assertSee('October 19, 2006')
        ->assertSee('Assigned TROP-9 · Main branch')
        ->assertSee('Org Chart 2024 · May 2, 2024');
});

test('a member without a birth year shows "birth year missing" instead of an age', function () {
    $member = Member::factory()->withoutBirthYear()->create();

    $this->get(route('members.show', $member))->assertSee('Birth year missing');
});

test('attendance counts present activities out of all activities', function () {
    $member = Member::factory()->create();
    [$wake, $bonding, $outing] = Activity::factory()->count(3)->create();
    Attendance::factory()->for($member)->for($wake)->create();
    Attendance::factory()->for($member)->for($bonding)->create(['status' => AttendanceStatus::Excused]);

    $component = Livewire::test('pages::members.show', ['member' => $member]);

    expect($component->instance()->attendedCount)->toBe(1)
        ->and($component->instance()->attendanceFor($outing))->toBe(AttendanceStatus::Absent)
        ->and($component->instance()->attendanceFor($bonding))->toBe(AttendanceStatus::Excused);
});

test('private notes are saved, and clearing them removes the note', function () {
    $member = Member::factory()->create();

    Livewire::test('pages::members.show', ['member' => $member])
        ->set('privateNotes', '  Prefers to be called Dave. ')
        ->assertHasNoErrors()
        ->assertSet('notesSaved', true);

    expect($member->fresh()->private_notes)->toBe('Prefers to be called Dave.');

    Livewire::test('pages::members.show', ['member' => $member->fresh()])
        ->assertSet('privateNotes', 'Prefers to be called Dave.')
        ->set('privateNotes', '');

    expect($member->fresh()->private_notes)->toBeNull();
});

test('a removed member\'s profile is not found', function () {
    $member = Member::factory()->create();
    $member->delete();

    $this->get(route('members.show', $member))->assertNotFound();
});
