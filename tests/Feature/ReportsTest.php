<?php

use App\Enums\AttendanceStatus;
use App\Enums\MemberStatus;
use App\Models\Activity;
use App\Models\Member;
use App\Models\User;
use App\Services\AttendanceReport;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00', 'Asia/Manila'));

    $this->ana = Member::factory()->create(['full_name' => 'Ana Cruz']);
    $this->ben = Member::factory()->create(['full_name' => 'Ben Reyes']);
    $this->cris = Member::factory()->create(['full_name' => 'Cris Lim']);
    $this->dan = Member::factory()->inactive()->create(['full_name' => 'Dan Uy']);

    $this->yearEnd = Activity::factory()->create(['name' => 'Year-End Bonding', 'starts_on' => '2025-12-30', 'ends_on' => null]);
    $this->outing = Activity::factory()->create(['name' => 'Leiana Outing', 'starts_on' => '2024-05-26', 'ends_on' => null]);
    $this->untitled = Activity::factory()->create(['name' => null, 'starts_on' => null, 'ends_on' => null]);

    $this->yearEnd->recordAttendance($this->ana, AttendanceStatus::Present);
    $this->yearEnd->recordAttendance($this->ben, AttendanceStatus::Excused);
    $this->yearEnd->recordAttendance($this->dan, AttendanceStatus::Present);
    $this->outing->recordAttendance($this->ana, AttendanceStatus::Present);
    $this->outing->recordAttendance($this->cris, AttendanceStatus::Exempt);
});

test('turnout per activity counts present, excused and absent against the active members expected', function () {
    $rows = collect(AttendanceReport::forYear(null)->activityRows())
        ->map(fn (array $row) => [$row['activity']->display_name, $row['present'], $row['excused'], $row['absent'], $row['expected']])
        ->all();

    expect($rows)->toBe([
        ['Year-End Bonding', 2, 1, 0, 3],
        ['Leiana Outing', 1, 0, 1, 2],
        ['Untitled activity', 0, 0, 3, 3],
    ]);

    // (2 + 1 + 0) present ÷ (3 + 2 + 3) expected
    expect(AttendanceReport::forYear(null)->summary()['average_turnout'])->toBe(3 / 8);
});

test('a year only includes activities dated in that year', function () {
    $report = AttendanceReport::forYear(2024);

    expect(AttendanceReport::years())->toBe([2025, 2024])
        ->and($report->activities()->pluck('name')->all())->toBe(['Leiana Outing'])
        ->and($report->summary()['average_turnout'])->toBe(0.5);
});

test('member rates leave out activities the member was not required at, lowest first', function () {
    $rows = collect(AttendanceReport::forYear(null)->memberRows(MemberStatus::Active))
        ->map(fn (array $row) => [$row['member']->full_name, $row['attended'], $row['required'], $row['excused']])
        ->all();

    expect($rows)->toBe([
        ['Ben Reyes', 0, 3, 1],
        ['Cris Lim', 0, 2, 0],
        ['Ana Cruz', 2, 3, 0],
    ]);

    $in2024 = collect(AttendanceReport::forYear(2024)->memberRows(MemberStatus::Active, lowestFirst: false));

    expect($in2024->pluck('member.full_name')->all())->toBe(['Ana Cruz', 'Ben Reyes', 'Cris Lim'])
        ->and($in2024->last()['rate'])->toBeNull();
});

describe('the reports page', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
    });

    test('shows turnout, member attendance and follows the period filter', function () {
        Livewire::test('pages::reports')
            ->assertSee('38%')
            ->assertSee('Year-End Bonding')
            ->assertSee('Ana Cruz')
            ->assertDontSee('Dan Uy')
            ->assertSet('lowAttendance', 2)
            ->set('period', '2024')
            ->assertSee('50%')
            ->assertDontSee('Year-End Bonding')
            ->set('memberStatus', 'inactive')
            ->assertSee('Dan Uy')
            ->assertDontSee('Ana Cruz');
    });

    test('an unknown period falls back to all time', function () {
        Livewire::test('pages::reports')
            ->set('period', '1999')
            ->assertSet('year', null)
            ->assertSee('Year-End Bonding');
    });
});

describe('downloads', function () {
    test('need a signed-in user', function (string $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    })->with(['reports.members', 'reports.attendance']);

    test('the member list has details and attendance but never private notes', function () {
        $this->ana->forceFill(['private_notes' => 'Secret family matter', 'nickname' => 'Ann'])->save();

        $response = $this->actingAs(User::factory()->create())->get(route('reports.members'));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        expect($response->headers->get('Cache-Control'))->toContain('no-store');

        $csv = $response->streamedContent();
        expect($csv)->toStartWith("\xEF\xBB\xBF")
            ->toContain('"Member no.","Full name",Nickname,Status')
            ->toContain('"Ana Cruz",Ann,Active')
            ->toContain(',2,3,0,67%')
            ->not->toContain('Secret family matter');
    });

    test('the attendance sheet has one column per activity in the period', function () {
        $csv = $this->actingAs(User::factory()->create())
            ->get(route('reports.attendance', ['year' => 2025]))
            ->streamedContent();

        expect($csv)->toContain('Member,Status,"Year-End Bonding (Dec 30, 2025)"')
            ->toContain('"Ben Reyes",Active,Excused')
            ->toContain('"Dan Uy",Inactive,Present')
            ->not->toContain('Leiana Outing');
    });

    test('cells that a spreadsheet would run as a formula are kept as text', function () {
        Member::factory()->create(['full_name' => '=HYPERLINK("http://example.test")']);

        $csv = $this->actingAs(User::factory()->create())->get(route('reports.attendance'))->streamedContent();

        expect($csv)->toContain("\"'=HYPERLINK(\"\"http://example.test\"\")\"")
            ->not->toContain("\n\"=HYPERLINK");
    });
});
