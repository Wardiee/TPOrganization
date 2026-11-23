<?php

use App\Enums\AttendanceStatus;
use App\Enums\MemberStatus;
use App\Livewire\Forms\ActivityForm;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Activities & attendance')] class extends Component
{
    #[Url(as: 'activity', except: null)]
    public ?int $selectedId = null;

    #[Url(except: 'take')]
    public string $view = 'take';

    public ActivityForm $form;

    public bool $formOpen = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Activity::class);
    }

    /**
     * Activities, newest recorded first, with present counts.
     *
     * @return Collection<int, Activity>
     */
    #[Computed]
    public function activities(): Collection
    {
        return Activity::query()
            ->withCount(['attendances as present_count' => fn ($query) => $query->where('status', AttendanceStatus::Present)])
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function selected(): ?Activity
    {
        return $this->activities->firstWhere('id', $this->selectedId) ?? $this->activities->first();
    }

    #[Computed]
    public function activeCount(): int
    {
        return Member::active()->count();
    }

    /**
     * Who to show for the selected activity: active members, plus anyone already marked for it.
     *
     * @return Collection<int, Member>
     */
    #[Computed]
    public function rows(): Collection
    {
        $activity = $this->selected;

        if ($activity === null) {
            return collect();
        }

        $members = Member::query()
            ->where(fn ($query) => $query
                ->where('status', MemberStatus::Active)
                ->orWhereHas('attendances', fn ($attendance) => $attendance->whereBelongsTo($activity)))
            ->with(['chapter', 'currentAssignments.position'])
            ->get();

        return $this->sortForAttendance($members);
    }

    /**
     * @return array<int, AttendanceStatus> member id => status; missing means absent
     */
    #[Computed]
    public function marks(): array
    {
        if ($this->selected === null) {
            return [];
        }

        return $this->selected->attendances()->get(['member_id', 'status'])
            ->mapWithKeys(fn (Attendance $attendance) => [$attendance->member_id => $attendance->status])
            ->all();
    }

    /**
     * Counts for the listed members. Turnout is present over active members expected to attend
     * (those marked "not required" are left out).
     *
     * @return array{present: int, excused: int, absent: int, exempt: int, expected: int, turnout: int}
     */
    #[Computed]
    public function totals(): array
    {
        $statuses = $this->rows->map(fn (Member $member) => $this->markFor($member));
        $count = fn (AttendanceStatus $wanted) => $statuses->filter(fn (AttendanceStatus $status) => $status === $wanted)->count();
        $present = $count(AttendanceStatus::Present);
        $expected = $this->selected?->expectedCount() ?? 0;

        return [
            'present' => $present,
            'excused' => $count(AttendanceStatus::Excused),
            'absent' => $count(AttendanceStatus::Absent),
            'exempt' => $count(AttendanceStatus::Exempt),
            'expected' => $expected,
            'turnout' => $expected > 0 ? (int) round($present / $expected * 100) : 0,
        ];
    }

    public function markFor(Member $member): AttendanceStatus
    {
        return $this->marks[$member->id] ?? AttendanceStatus::Absent;
    }

    public function selectActivity(int $activityId): void
    {
        $this->selectedId = $activityId;
    }

    public function setStatus(int $memberId, string $status): void
    {
        $activity = $this->selected;
        abort_if($activity === null, 404);
        $this->authorize('update', $activity);

        validator(['status' => $status], ['status' => ['required', Rule::enum(AttendanceStatus::class)]])->validate();

        $activity->recordAttendance(Member::findOrFail($memberId), AttendanceStatus::from($status));

        unset($this->marks, $this->totals, $this->activities);
    }

    /**
     * Mark every active member present for the selected activity, except those marked "not required".
     */
    public function markAllPresent(): void
    {
        $activity = $this->selected;
        abort_if($activity === null, 404);
        $this->authorize('update', $activity);

        Member::active()->get()
            ->reject(fn (Member $member) => $this->markFor($member) === AttendanceStatus::Exempt)
            ->each(fn (Member $member) => $activity->recordAttendance($member, AttendanceStatus::Present));

        unset($this->marks, $this->totals, $this->activities, $this->rows);
    }

    /**
     * The question asked before deleting, with how much attendance goes with it.
     */
    public function deleteConfirmation(): string
    {
        $activity = $this->selected;
        $marks = $activity?->attendances()->count() ?? 0;

        return "Delete “{$activity?->display_name}” ({$activity?->date_label})?"
            .($marks > 0 ? " Its {$marks} attendance ".Str::plural('mark', $marks).' will be hidden too.' : '');
    }

    /**
     * Delete (soft) the selected activity and select the newest remaining one.
     */
    public function deleteActivity(): void
    {
        $activity = $this->selected;
        abort_if($activity === null, 404);
        $this->authorize('delete', $activity);

        $activity->deleteWithHistory();

        $this->selectedId = null;
        unset($this->activities, $this->selected, $this->rows, $this->marks, $this->totals);
    }

    public function openCreate(): void
    {
        $this->authorize('create', Activity::class);

        $this->form->reset();
        $this->resetValidation();
        $this->formOpen = true;
    }

    public function openEdit(): void
    {
        abort_if($this->selected === null, 404);
        $this->authorize('update', $this->selected);

        $this->form->reset();
        $this->resetValidation();
        $this->form->setActivity($this->selected);
        $this->formOpen = true;
    }

    public function saveActivity(): void
    {
        if ($this->form->activity === null) {
            $this->authorize('create', Activity::class);
            $activity = $this->form->store();
        } else {
            $this->authorize('update', $this->form->activity);
            $activity = $this->form->update();
        }

        $this->selectedId = $activity->id;
        $this->formOpen = false;
        $this->form->reset();

        unset($this->activities, $this->rows, $this->marks, $this->totals);
    }

    public function closeForm(): void
    {
        $this->formOpen = false;
        $this->resetValidation();
    }

    /**
     * Overview grid columns: every activity, oldest recorded first.
     *
     * @return Collection<int, Activity>
     */
    #[Computed]
    public function gridActivities(): Collection
    {
        return $this->activities->reverse()->values();
    }

    /**
     * Overview grid rows: active members plus anyone with any attendance on record.
     *
     * @return Collection<int, Member>
     */
    #[Computed]
    public function gridMembers(): Collection
    {
        $members = Member::query()
            ->where(fn ($query) => $query->where('status', MemberStatus::Active)->orWhereHas('attendances', fn ($attendance) => $attendance->whereHas('activity')))
            ->with(['currentAssignments.position'])
            ->get();

        return $this->sortForAttendance($members);
    }

    /**
     * @return array<int, array<int, AttendanceStatus>> member id => activity id => status
     */
    #[Computed]
    public function gridMarks(): array
    {
        $marks = [];

        Attendance::query()->get(['member_id', 'activity_id', 'status'])->each(function (Attendance $attendance) use (&$marks): void {
            $marks[$attendance->member_id][$attendance->activity_id] = $attendance->status;
        });

        return $marks;
    }

    /**
     * Active members first in seat order (TROP, then TROPS, then no seat), then everyone else by name.
     *
     * @param  Collection<int, Member>  $members
     * @return Collection<int, Member>
     */
    private function sortForAttendance(Collection $members): Collection
    {
        return $members->sort(function (Member $a, Member $b): int {
            $activeA = $a->status === MemberStatus::Active ? 0 : 1;
            $activeB = $b->status === MemberStatus::Active ? 0 : 1;

            return [$activeA, $a->primaryPosition()?->sort ?? PHP_INT_MAX, $a->full_name]
                <=> [$activeB, $b->primaryPosition()?->sort ?? PHP_INT_MAX, $b->full_name];
        })->values();
    }
};
?>

@php
    $activity = $this->selected;
    $totals = $this->totals;
    $statusOptions = [
        ['status' => App\Enums\AttendanceStatus::Present, 'short' => 'P', 'on' => 'bg-blue text-white'],
        ['status' => App\Enums\AttendanceStatus::Excused, 'short' => 'E', 'on' => 'bg-[#6B6A62] text-white'],
        ['status' => App\Enums\AttendanceStatus::Absent, 'short' => 'A', 'on' => 'bg-absent text-white'],
        ['status' => App\Enums\AttendanceStatus::Exempt, 'short' => 'N/A', 'on' => 'bg-[#0A5F63] text-white'],
    ];
    $input = 'min-h-11 w-full rounded-[10px] border border-line-input bg-white px-3 text-[15px] text-ink focus-visible:outline-2 focus-visible:outline-blue aria-invalid:border-absent';
@endphp

{{-- Designs: docs/design/web/Activities.dc.html (lg+), docs/design/mobile/Attendance.dc.html (phones) --}}
<div class="contents">
    {{-- ───────────── Phone header: title, new activity, activity chips ───────────── --}}
    <header class="-mx-4 flex flex-col gap-3 border-b border-line bg-bg pt-4 pb-3 lg:hidden">
        <div class="flex items-center justify-between gap-3 px-4">
            <h1 class="font-display text-[28px] font-extrabold tracking-[-0.01em]">Attendance</h1>
            @can('create', App\Models\Activity::class)
                <button type="button" wire:click="openCreate" class="inline-flex min-h-11 items-center gap-1.5 rounded-full border border-line-input bg-white px-3.5 text-[13px] font-semibold text-ink focus-visible:outline-2 focus-visible:outline-blue">
                    <x-icon name="plus" :size="16" :stroke-width="2.4" />Activity
                </button>
            @endcan
        </div>
        <div role="group" aria-label="Choose activity" class="flex gap-2 overflow-x-auto px-4">
            @foreach ($this->activities as $item)
                @php($on = $activity?->is($item))
                <button type="button" wire:key="chip-{{ $item->id }}" wire:click="selectActivity({{ $item->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}" @class([
                    'flex min-h-13 shrink-0 flex-col items-start gap-0.5 rounded-xl border px-3.5 py-2 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue',
                    'border-navy bg-navy text-white' => $on,
                    'border-line bg-white text-ink' => ! $on,
                ])>
                    <span class="text-[13px] font-bold whitespace-nowrap">{{ filled($item->name) ? $item->name : 'Untitled' }}</span>
                    <span @class(['text-[11px] whitespace-nowrap', 'text-navy-text' => $on, 'text-muted' => ! $on])>{{ $item->starts_on ? $item->date_label : 'No date' }}</span>
                </button>
            @endforeach
        </div>
    </header>

    {{-- ───────────── Desktop header ───────────── --}}
    <header class="hidden flex-wrap items-end justify-between gap-4 lg:flex">
        <div class="flex flex-col gap-1.5">
            <h1 class="font-display text-[32px] font-extrabold tracking-[-0.01em]">Activities &amp; Attendance</h1>
            <p class="text-sm text-muted">{{ $this->activities->count() }} {{ Str::plural('activity', $this->activities->count()) }} recorded · attendance for {{ $this->activeCount }} active members</p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <div role="group" aria-label="View" class="flex gap-1 rounded-xl bg-[#ECEAE0] p-1">
                @foreach (['take' => 'Take attendance', 'grid' => 'Overview grid'] as $value => $label)
                    <button type="button" wire:click="$set('view', '{{ $value }}')" aria-pressed="{{ $view === $value ? 'true' : 'false' }}" @class([
                        'min-h-10 rounded-[9px] px-3.5 text-sm font-semibold text-ink focus-visible:outline-2 focus-visible:outline-blue',
                        'bg-white shadow-[0_1px_3px_rgba(22,24,29,0.12)]' => $view === $value,
                    ])>{{ $label }}</button>
                @endforeach
            </div>
            @can('create', App\Models\Activity::class)
                <button type="button" wire:click="openCreate" class="inline-flex min-h-11 items-center gap-2 rounded-[10px] bg-blue px-[18px] text-sm font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                    <x-icon name="plus" :size="16" :stroke-width="2.4" />New activity
                </button>
            @endcan
        </div>
    </header>

    @if ($activity === null)
        <x-card class="flex flex-col items-start gap-2 p-6">
            <p class="font-semibold">No activities yet</p>
            <p class="text-sm text-muted">Add the first activity, then take attendance for it.</p>
        </x-card>
    @else
        {{-- ───────────── Phone: totals, rows, save bar ───────────── --}}
        <section aria-label="Totals" class="grid grid-cols-3 gap-2 lg:hidden">
            <div class="flex flex-col gap-0.5 rounded-xl bg-blue px-3 py-2.5 text-white"><span class="font-display text-2xl leading-tight font-extrabold">{{ $totals['present'] }}</span><span class="text-xs font-semibold">Present</span></div>
            <div class="flex flex-col gap-0.5 rounded-xl border border-line bg-white px-3 py-2.5"><span class="font-display text-2xl leading-tight font-extrabold">{{ $totals['excused'] }}</span><span class="text-xs font-semibold text-muted">Excused</span></div>
            <div class="flex flex-col gap-0.5 rounded-xl border border-line bg-white px-3 py-2.5"><span class="font-display text-2xl leading-tight font-extrabold">{{ $totals['absent'] }}</span><span class="text-xs font-semibold text-muted">Absent</span></div>
        </section>

        <div class="-mb-2 flex flex-col gap-0.5 lg:hidden">
            <p class="text-[13px] text-muted">
                <span class="font-semibold text-ink">{{ $activity->display_name }}</span> · {{ $activity->date_label }}
                · {{ $totals['turnout'] }}% of {{ $totals['expected'] }} expected
                @if ($totals['exempt'] > 0) · {{ $totals['exempt'] }} not required @endif
            </p>
            <div class="-ml-1 flex flex-wrap items-center gap-x-1">
                @can('update', $activity)
                    <button type="button" wire:click="openEdit" class="inline-flex min-h-11 items-center px-1 text-[13px] font-semibold text-blue">{{ filled($activity->name) && $activity->starts_on ? 'Edit details' : 'Add details' }}</button>
                    <button type="button" wire:click="markAllPresent" wire:confirm="Mark all active members present for {{ $activity->display_name }}? Members marked “not required” stay as they are." class="inline-flex min-h-11 items-center px-1 text-[13px] font-semibold text-blue">Mark all present</button>
                @endcan
                @can('delete', $activity)
                    <button type="button" wire:click="deleteActivity" wire:confirm="{{ $this->deleteConfirmation() }}" class="ml-auto inline-flex min-h-11 items-center px-1 text-[13px] font-semibold text-absent">Delete</button>
                @endcan
            </div>
        </div>

        <ul class="-mx-4 border-t border-line bg-white lg:hidden">
            @foreach ($this->rows as $member)
                @php($mark = $this->markFor($member))
                <li wire:key="phone-row-{{ $activity->id }}-{{ $member->id }}" class="flex min-h-16 items-center gap-2.5 border-b border-line-soft py-2 pr-3 pl-4">
                    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span class="truncate text-sm font-semibold">{{ $member->full_name }}</span>
                        <span class="truncate text-xs text-muted">
                            {{ $member->primaryPosition()?->code ?? 'No position' }}{{ $member->chapter ? ' · '.$member->chapter->name : '' }}
                            @if ($member->status !== App\Enums\MemberStatus::Active) · <span class="font-semibold">{{ $member->status->label() }}</span> @endif
                        </span>
                    </span>
                    <span role="group" aria-label="Attendance for {{ $member->full_name }}" class="flex shrink-0 gap-0.5 rounded-[11px] bg-[#F0EFE8] p-[3px]">
                        @foreach ($statusOptions as $option)
                            @php($on = $mark === $option['status'])
                            <button type="button" wire:click="setStatus({{ $member->id }}, '{{ $option['status']->value }}')" aria-pressed="{{ $on ? 'true' : 'false' }}" aria-label="{{ $option['status']->label() }}" @can('update', $activity) @else disabled @endcan @class([
                                'size-11 rounded-lg text-sm font-bold focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-blue',
                                $option['on'] => $on,
                                'text-[#4A4D58]' => ! $on,
                            ])>{{ $option['short'] }}</button>
                        @endforeach
                    </span>
                </li>
            @endforeach
        </ul>

        <div class="sticky bottom-[calc(59px+max(8px,env(safe-area-inset-bottom)))] z-20 -mx-4 border-t border-line bg-white px-4 py-2.5 lg:hidden" x-data="{ saved: false }">
            <button type="button" wire:click="$refresh" x-on:click="saved = true; setTimeout(() => saved = false, 2500)" class="min-h-[50px] w-full rounded-xl bg-navy text-[15px] font-bold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                <span x-show="! saved">Save attendance · {{ $totals['turnout'] }}% present</span>
                <span x-show="saved" x-cloak>Saved · {{ $totals['turnout'] }}% present</span>
            </button>
            <p class="sr-only" role="status" x-text="saved ? 'Attendance saved' : ''"></p>
        </div>

        {{-- ───────────── Desktop: take attendance ───────────── --}}
        @if ($view === 'take')
            <div class="hidden flex-wrap items-start gap-5 lg:flex">
                <section aria-label="Activities" class="flex min-w-0 flex-[1_1_300px] flex-col gap-2.5">
                    @foreach ($this->activities as $item)
                        @php($on = $activity->is($item))
                        <button type="button" wire:key="activity-{{ $item->id }}" wire:click="selectActivity({{ $item->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}" @class([
                            'flex w-full flex-col gap-2 rounded-[14px] border px-4 py-3.5 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue',
                            'border-navy bg-navy text-white' => $on,
                            'border-line bg-white text-ink hover:border-line-input' => ! $on,
                        ])>
                            <span class="flex w-full items-start justify-between gap-2.5">
                                <span class="flex flex-col gap-[3px]">
                                    <span class="text-[15px] font-bold">{{ $item->display_name }}</span>
                                    <span @class(['text-[13px]', 'text-navy-text' => $on, 'text-muted' => ! $on])>{{ $item->date_label }}</span>
                                </span>
                                <span class="font-display text-[22px] leading-none font-extrabold">{{ $item->present_count }}<span class="sr-only"> present</span></span>
                            </span>
                            @if (blank($item->name))
                                <x-tag class="self-start bg-floating-bg text-floating-fg">NEEDS A NAME</x-tag>
                            @endif
                        </button>
                    @endforeach
                </section>

                <section aria-labelledby="take-heading" class="min-w-0 flex-[999_1_560px] overflow-hidden rounded-2xl border border-line bg-white">
                    <div class="flex flex-col gap-3.5 border-b border-line px-6 py-5">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div class="flex flex-col gap-1">
                                <p class="text-xs font-bold tracking-[0.08em] text-muted">ATTENDANCE</p>
                                <h2 id="take-heading" class="font-display text-2xl font-extrabold">{{ $activity->display_name }}</h2>
                                <p class="text-sm text-muted">{{ $activity->date_label }}{{ $activity->location ? ' · '.$activity->location : '' }}</p>
                            </div>
                            @can('update', $activity)
                                <div class="flex gap-2">
                                    <button type="button" wire:click="openEdit" class="min-h-11 rounded-[10px] border border-line-input bg-white px-3.5 text-[13px] font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue">Edit details</button>
                                    @can('delete', $activity)
                                        <button type="button" wire:click="deleteActivity" wire:confirm="{{ $this->deleteConfirmation() }}" class="min-h-11 rounded-[10px] border border-line-input bg-white px-3.5 text-[13px] font-semibold text-absent hover:bg-[#FFF4EC] focus-visible:outline-2 focus-visible:outline-blue">Delete</button>
                                    @endcan
                                    <button type="button" wire:click="markAllPresent" wire:confirm="Mark all active members present for {{ $activity->display_name }}? Members marked “not required” stay as they are." class="min-h-11 rounded-[10px] border border-line-input bg-white px-3.5 text-[13px] font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue">Mark all present</button>
                                </div>
                            @endcan
                        </div>
                        <div class="flex flex-wrap gap-2.5">
                            <span class="flex flex-[1_1_120px] flex-col gap-0.5 rounded-xl bg-info-bg px-3.5 py-3"><span class="text-xs font-semibold text-info-fg">Present</span><span class="font-display text-2xl font-extrabold">{{ $totals['present'] }}</span></span>
                            <span class="flex flex-[1_1_120px] flex-col gap-0.5 rounded-xl bg-[#F0EFE8] px-3.5 py-3"><span class="text-xs font-semibold text-inactive-fg">Excused</span><span class="font-display text-2xl font-extrabold">{{ $totals['excused'] }}</span></span>
                            <span class="flex flex-[1_1_120px] flex-col gap-0.5 rounded-xl bg-[#FFF4EC] px-3.5 py-3"><span class="text-xs font-semibold text-alert-fg">Absent</span><span class="font-display text-2xl font-extrabold">{{ $totals['absent'] }}</span></span>
                            <span class="flex flex-[1_1_120px] flex-col gap-0.5 rounded-xl bg-[#DDF1F2] px-3.5 py-3"><span class="text-xs font-semibold text-[#0A5F63]">Not required</span><span class="font-display text-2xl font-extrabold">{{ $totals['exempt'] }}</span></span>
                            <span class="flex flex-[1_1_120px] flex-col gap-0.5 rounded-xl bg-navy px-3.5 py-3 text-white"><span class="text-xs font-semibold text-yellow">Turnout</span><span class="font-display text-2xl font-extrabold">{{ $totals['turnout'] }}%</span><span class="text-[11px] text-navy-text">of {{ $totals['expected'] }} expected</span></span>
                        </div>
                    </div>
                    <ul>
                        @foreach ($this->rows as $member)
                            @php($mark = $this->markFor($member))
                            <li wire:key="row-{{ $activity->id }}-{{ $member->id }}" class="flex flex-wrap items-center justify-between gap-2.5 border-b border-line-soft px-6 py-2">
                                <span class="flex min-w-0 flex-[1_1_220px] items-center gap-3">
                                    <span class="min-w-14 rounded-md bg-[#EEF1FC] px-1.5 py-[3px] text-center text-[11px] font-extrabold text-info-fg">{{ $member->primaryPosition()?->code ?? '—' }}</span>
                                    <span class="flex min-w-0 flex-col gap-px">
                                        <span class="text-sm font-semibold">{{ $member->full_name }}</span>
                                        <span class="text-xs text-muted">{{ $member->chapter?->name }}@if ($member->status !== App\Enums\MemberStatus::Active) · <span class="font-semibold">{{ $member->status->label() }}</span>@endif</span>
                                    </span>
                                </span>
                                <span role="group" aria-label="Attendance for {{ $member->full_name }}" class="flex gap-1 rounded-[10px] bg-[#F3F2EC] p-[3px]">
                                    @foreach ($statusOptions as $option)
                                        @php($on = $mark === $option['status'])
                                        <button type="button" wire:click="setStatus({{ $member->id }}, '{{ $option['status']->value }}')" aria-pressed="{{ $on ? 'true' : 'false' }}" @can('update', $activity) @else disabled @endcan @class([
                                            'min-h-11 min-w-[76px] rounded-lg px-2.5 text-[13px] font-semibold focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-blue',
                                            $option['on'] => $on,
                                            'text-[#4A4D58] hover:bg-white' => ! $on,
                                        ])>{{ $option['status']->label() }}</button>
                                    @endforeach
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        @else
            {{-- ───────────── Desktop: overview grid ───────────── --}}
            @php($columns = 'grid-template-columns: minmax(240px, 2fr) repeat('.$this->gridActivities->count().', minmax(72px, 1fr)) 80px')
            <section aria-label="Attendance overview" class="hidden overflow-hidden rounded-2xl border border-line bg-white lg:block">
                <div class="overflow-x-auto">
                    <div class="min-w-[900px]" role="table" aria-label="Attendance by member and activity">
                        <div role="row" class="grid items-end gap-2 border-b border-line bg-[#FAF9F4] px-5 py-3" style="{{ $columns }}">
                            <span role="columnheader" class="text-xs font-bold tracking-[0.06em] text-muted">MEMBER</span>
                            @foreach ($this->gridActivities as $item)
                                <span role="columnheader" wire:key="head-{{ $item->id }}" class="flex flex-col gap-0.5 text-center">
                                    <span class="text-xs font-bold">{{ filled($item->name) ? $item->name : 'Untitled' }}</span>
                                    <span class="text-[11px] text-muted">{{ $item->date_label }}</span>
                                </span>
                            @endforeach
                            <span role="columnheader" class="text-center text-xs font-bold tracking-[0.06em] text-muted">TOTAL</span>
                        </div>
                        @foreach ($this->gridMembers as $member)
                            <div role="row" wire:key="grid-{{ $member->id }}" class="grid items-center gap-2 border-b border-line-soft px-5 py-2" style="{{ $columns }}">
                                <span role="rowheader" class="flex min-w-0 items-center gap-2.5">
                                    <span class="min-w-14 text-[11px] font-extrabold text-info-fg">{{ $member->primaryPosition()?->code ?? '—' }}</span>
                                    <span class="truncate text-sm font-semibold">{{ $member->full_name }}</span>
                                    @if ($member->status !== App\Enums\MemberStatus::Active)
                                        <x-status-pill :status="$member->status" size="sm" />
                                    @endif
                                </span>
                                @php($attended = 0)
                                @php($exemptCount = 0)
                                @foreach ($this->gridActivities as $item)
                                    @php($cell = $this->gridMarks[$member->id][$item->id] ?? App\Enums\AttendanceStatus::Absent)
                                    @php($attended += $cell === App\Enums\AttendanceStatus::Present ? 1 : 0)
                                    @php($exemptCount += $cell === App\Enums\AttendanceStatus::Exempt ? 1 : 0)
                                    <span role="cell" class="flex justify-center">
                                        <span title="{{ $item->display_name }}: {{ Str::lower($cell->label()) }}" @class([
                                            'flex size-7 items-center justify-center rounded-lg text-[13px] font-bold',
                                            'bg-blue text-white' => $cell === App\Enums\AttendanceStatus::Present,
                                            'bg-[#E2E0D6] text-inactive-fg' => $cell === App\Enums\AttendanceStatus::Excused,
                                            'border border-[#E2E0D6]' => $cell === App\Enums\AttendanceStatus::Absent,
                                            'bg-[#DDF1F2] text-[#0A5F63]' => $cell === App\Enums\AttendanceStatus::Exempt,
                                        ])>
                                            @if ($cell === App\Enums\AttendanceStatus::Present)
                                                <x-icon name="check" :size="14" :stroke-width="3" />
                                            @elseif ($cell === App\Enums\AttendanceStatus::Excused)
                                                <span aria-hidden="true">E</span>
                                            @elseif ($cell === App\Enums\AttendanceStatus::Exempt)
                                                <span aria-hidden="true">–</span>
                                            @endif
                                            <span class="sr-only">{{ $cell->label() }}</span>
                                        </span>
                                    </span>
                                @endforeach
                                <span role="cell" class="text-center text-sm font-bold">{{ $attended }}/{{ $this->gridActivities->count() - $exemptCount }}</span>
                            </div>
                        @endforeach
                        <div role="row" class="grid items-center gap-2 bg-navy px-5 py-3 text-white" style="{{ $columns }}">
                            <span role="rowheader" class="text-[13px] font-bold">Attended (all members)</span>
                            @foreach ($this->gridActivities as $item)
                                <span role="cell" wire:key="foot-{{ $item->id }}" class="text-center font-display text-lg font-extrabold text-yellow">{{ $item->present_count }}</span>
                            @endforeach
                            <span></span>
                        </div>
                    </div>
                </div>
            </section>
        @endif
    @endif

    {{-- ───────────── New / edit activity dialog ───────────── --}}
    @if ($formOpen)
        {{-- wire:ignore.self keeps Livewire from stripping the dialog's "open" state when the form re-renders. --}}
        <dialog
            wire:ignore.self
            x-data
            x-init="$el.showModal()"
            x-on:close="$wire.closeForm()"
            aria-labelledby="activity-form-heading"
            class="m-auto w-[min(32rem,calc(100%-2rem))] rounded-2xl border border-line bg-white p-0 text-ink shadow-xl backdrop:bg-ink/50"
        >
            <form wire:submit="saveActivity" class="flex flex-col gap-4 p-5 lg:p-6" novalidate>
                <div class="flex items-start justify-between gap-3">
                    <div class="flex flex-col gap-1">
                        <h2 id="activity-form-heading" class="font-display text-[22px] font-extrabold">{{ $form->activity ? 'Edit activity' : 'New activity' }}</h2>
                        <p class="text-[13px] text-muted">Name and date are optional. You can add them later.</p>
                    </div>
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue" aria-label="Close">
                        <x-icon name="close" :size="20" />
                    </button>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="activity-name" class="text-sm font-semibold">Name <span class="font-normal text-muted">(optional)</span></label>
                    <input id="activity-name" type="text" wire:model="form.name" placeholder="e.g. Year-End Bonding" autocomplete="off" @error('form.name') aria-invalid="true" @enderror class="{{ $input }}">
                    @error('form.name') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="activity-starts" class="text-sm font-semibold">Date <span class="font-normal text-muted">(optional)</span></label>
                        <input id="activity-starts" type="date" wire:model="form.starts_on" @error('form.starts_on') aria-invalid="true" @enderror class="{{ $input }}">
                        @error('form.starts_on') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="activity-ends" class="text-sm font-semibold">End date <span class="font-normal text-muted">(if more than a day)</span></label>
                        <input id="activity-ends" type="date" wire:model="form.ends_on" @error('form.ends_on') aria-invalid="true" @enderror class="{{ $input }}">
                        @error('form.ends_on') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="activity-location" class="text-sm font-semibold">Location <span class="font-normal text-muted">(optional)</span></label>
                    <input id="activity-location" type="text" wire:model="form.location" autocomplete="off" class="{{ $input }}">
                    @error('form.location') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="activity-notes" class="text-sm font-semibold">Notes <span class="font-normal text-muted">(optional)</span></label>
                    <textarea id="activity-notes" rows="3" wire:model="form.notes" class="{{ $input }} py-2"></textarea>
                    @error('form.notes') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="min-h-11 rounded-[10px] border border-line-input bg-white px-5 text-sm font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue">Cancel</button>
                    <button type="submit" class="min-h-11 rounded-[10px] bg-blue px-6 text-sm font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                        {{ $form->activity ? 'Save changes' : 'Add activity' }}
                    </button>
                </div>
            </form>
        </dialog>
    @endif
</div>
