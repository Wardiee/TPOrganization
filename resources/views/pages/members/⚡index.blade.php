<?php

use App\Enums\AttendanceStatus;
use App\Enums\HistoryKind;
use App\Enums\MemberStatus;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Chapter;
use App\Models\HistoryEvent;
use App\Models\Member;
use App\Services\NameMismatches;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Members')] class extends Component
{
    /** Number of recent activities shown as attendance dots. */
    private const ATTENDANCE_DOTS = 6;

    #[Url(as: 'status', except: 'all')]
    public string $status = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $chapter = '';

    #[Url(as: 'missing', except: false)]
    public bool $missingOnly = false;

    public ?int $selectedId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Member::class);
    }

    /**
     * Every member, active first then floating then inactive, A to Z.
     *
     * @return Collection<int, Member>
     */
    #[Computed]
    public function members(): Collection
    {
        $statusOrder = array_flip(array_map(fn (MemberStatus $status) => $status->value, MemberStatus::cases()));

        return Member::query()
            ->with(['chapter', 'currentAssignments.position'])
            ->get()
            ->sortBy([
                fn (Member $a, Member $b) => $statusOrder[$a->status->value] <=> $statusOrder[$b->status->value],
                fn (Member $a, Member $b) => strcasecmp($a->full_name, $b->full_name),
            ])
            ->values();
    }

    /**
     * Members matching the status, search, chapter and "missing info only" filters.
     *
     * @return Collection<int, Member>
     */
    #[Computed]
    public function rows(): Collection
    {
        $search = Str::lower(trim($this->search));

        return $this->members->filter(function (Member $member) use ($search): bool {
            if ($this->status !== 'all' && $member->status->value !== $this->status) {
                return false;
            }

            if ($this->chapter !== '' && $member->chapter?->name !== $this->chapter) {
                return false;
            }

            if ($this->missingOnly && $this->notesFor($member) === []) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = Str::lower(implode(' ', [
                $member->full_name,
                $member->nickname,
                ...$member->currentPositions()->pluck('code'),
            ]));

            return Str::contains($haystack, $search);
        })->values();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function statusCounts(): array
    {
        $counts = ['all' => $this->members->count()];

        foreach (MemberStatus::cases() as $status) {
            $counts[$status->value] = $this->members->where('status', $status)->count();
        }

        return $counts;
    }

    /**
     * The most recent activities, oldest first, for the attendance dots.
     *
     * @return Collection<int, Activity>
     */
    #[Computed]
    public function recentActivities(): Collection
    {
        return Activity::query()->orderByDesc('id')->limit(self::ATTENDANCE_DOTS)->get()->reverse()->values();
    }

    /**
     * Attendance for the recent activities: [member id][activity id] => status. No entry means absent.
     *
     * @return array<int, array<int, AttendanceStatus>>
     */
    #[Computed]
    public function attendance(): array
    {
        $byMember = [];

        Attendance::query()
            ->whereIn('activity_id', $this->recentActivities->modelKeys())
            ->get(['member_id', 'activity_id', 'status'])
            ->each(function (Attendance $attendance) use (&$byMember): void {
                $byMember[$attendance->member_id][$attendance->activity_id] = $attendance->status;
            });

        return $byMember;
    }

    /**
     * @return array<int, array{member_id: int, member_name: string, position_code: string, name_as_listed: string}>
     */
    #[Computed]
    public function nameMismatches(): array
    {
        return app(NameMismatches::class)->find();
    }

    #[Computed]
    public function selected(): ?Member
    {
        return $this->members->firstWhere('id', $this->selectedId) ?? $this->rows->first();
    }

    #[Computed]
    public function lastUpdated(): ?string
    {
        return HistoryEvent::query()->where('kind', HistoryKind::Members)->max('happened_on');
    }

    public function select(int $memberId): void
    {
        $this->selectedId = $memberId;
    }

    /**
     * What still needs attention for a member: missing fields, then a name mismatch with the org chart.
     *
     * @return array{missing?: string, mismatch?: string}
     */
    public function notesFor(Member $member): array
    {
        $notes = [];

        if ($member->missing_fields !== []) {
            $notes['missing'] = 'Missing: '.implode(', ', $member->missing_fields);
        }

        if (isset($this->nameMismatches[$member->id])) {
            $notes['mismatch'] = 'Name differs on org chart';
        }

        return $notes;
    }

    public function attendanceStatus(Member $member, Activity $activity): AttendanceStatus
    {
        return $this->attendance[$member->id][$activity->id] ?? AttendanceStatus::Absent;
    }

    /**
     * Recent activities the member was expected at (not marked "not required").
     */
    public function expectedCount(Member $member): int
    {
        $exempt = collect($this->attendance[$member->id] ?? [])->filter(fn (AttendanceStatus $status) => $status === AttendanceStatus::Exempt)->count();

        return $this->recentActivities->count() - $exempt;
    }

    public function attendedCount(Member $member): int
    {
        return collect($this->attendance[$member->id] ?? [])->filter(fn (AttendanceStatus $status) => $status === AttendanceStatus::Present)->count();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function statusTabs(): array
    {
        return [
            ['value' => 'all', 'label' => 'All'],
            ...array_map(fn (MemberStatus $status) => ['value' => $status->value, 'label' => $status->label()], MemberStatus::cases()),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function chapterNames(): Collection
    {
        return Chapter::query()->orderBy('id')->pluck('name');
    }
};
?>

@php
    $tones = ['active' => 'active', 'floating' => 'floating', 'inactive' => 'inactive'];
@endphp

{{-- Designs: docs/design/web/Members.dc.html (lg+), docs/design/mobile/Members.dc.html (phones) --}}
<div class="contents">
    {{-- Phone header (sticky): title, count, add button, search, status chips --}}
    <header class="sticky top-0 z-20 -mx-4 flex flex-col gap-3 border-b border-line bg-bg px-4 pt-4 pb-3 lg:hidden">
        <div class="flex items-center justify-between gap-3">
            <div class="flex flex-col gap-0.5">
                <h1 class="font-display text-[28px] font-extrabold tracking-[-0.01em]">Members</h1>
                <p class="text-[13px] text-muted">{{ $this->statusCounts['all'] }} members · {{ $this->statusCounts['active'] }} active</p>
            </div>
            @can('create', App\Models\Member::class)
                <a href="{{ route('members.create') }}" wire:navigate aria-label="Add member" class="flex size-12 items-center justify-center rounded-full bg-blue text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                    <x-icon name="plus" :size="22" :stroke-width="2.4" />
                </a>
            @endcan
        </div>
        <label class="flex min-h-[46px] items-center gap-2.5 rounded-xl border border-line-input bg-white px-3.5 focus-within:outline-2 focus-within:outline-blue">
            <x-icon name="search" class="text-muted" />
            <span class="sr-only">Search members</span>
            <input type="search" wire:model.live.debounce.250ms="search" placeholder="Name, nickname or position" class="min-w-0 flex-1 bg-transparent text-[15px] text-ink outline-none placeholder:text-muted">
        </label>
        <div role="group" aria-label="Filter by status" class="-mx-4 flex gap-2 relative overflow-x-auto px-4">
            @foreach ($this->statusTabs() as $tab)
                @php($on = $status === $tab['value'])
                <button type="button" wire:click="$set('status', '{{ $tab['value'] }}')" aria-pressed="{{ $on ? 'true' : 'false' }}" @class([
                    'min-h-11 shrink-0 rounded-full border px-3.5 text-[13px] font-semibold whitespace-nowrap focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue',
                    'border-navy bg-navy text-white' => $on,
                    'border-line-input bg-white text-ink' => ! $on,
                ])>{{ $tab['label'] }} {{ $this->statusCounts[$tab['value']] }}</button>
            @endforeach
        </div>
    </header>

    {{-- Phone list --}}
    <section aria-label="Member list" class="-mx-4 -mt-4 bg-white lg:hidden">
        @if ($this->rows->isEmpty())
            <p class="px-6 py-12 text-center text-sm text-muted">
                {{ trim($search) === '' ? 'No members match these filters.' : 'No members match “'.trim($search).'”.' }}
            </p>
        @endif
        <ul>
            @foreach ($this->rows as $member)
                @php($notes = $this->notesFor($member))
                <li wire:key="phone-{{ $member->id }}" class="border-b border-line-soft">
                    <a href="{{ route('members.show', $member) }}" wire:navigate class="flex min-h-16 items-center gap-3 px-4 py-2.5 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue">
                        <x-avatar-initials :initials="$member->initials" :photo="$member->photoUrl()" size="touch" :tone="$tones[$member->status->value].'-solid'" />
                        <span class="flex min-w-0 flex-1 flex-col gap-[3px]">
                            <span class="truncate text-[15px] font-semibold">{{ $member->full_name }}</span>
                            <span class="truncate text-xs text-muted">
                                {{ collect([$member->nickname ? '“'.$member->nickname.'”' : null, $member->primaryPosition()?->code ?? 'No position', $member->chapter?->name])->filter()->implode(' · ') }}
                            </span>
                            @if (isset($notes['mismatch']))
                                <span class="text-xs font-semibold text-floating-fg">{{ $notes['mismatch'] }}</span>
                            @elseif (isset($notes['missing']))
                                <span class="text-xs font-semibold text-floating-fg">{{ $notes['missing'] }}</span>
                            @endif
                        </span>
                        <x-status-pill :status="$member->status" size="sm" />
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Desktop header --}}
    <header class="hidden flex-wrap items-end justify-between gap-4 lg:flex">
        <div class="flex flex-col gap-1.5">
            <h1 class="font-display text-[32px] font-extrabold tracking-[-0.01em]">Members</h1>
            <p class="text-sm text-muted">
                {{ $this->statusCounts['all'] }} members · {{ $this->chapterNames->count() }} chapters
                @if ($this->lastUpdated)
                    · last updated {{ \Carbon\CarbonImmutable::parse($this->lastUpdated)->format('M j, Y') }}
                @endif
            </p>
        </div>
        @can('create', App\Models\Member::class)
            <a href="{{ route('members.create') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-[10px] bg-blue px-[18px] text-sm font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                <x-icon name="plus" :size="16" :stroke-width="2.4" />
                Add member
            </a>
        @endcan
    </header>

    {{-- Desktop filters --}}
    <div class="hidden flex-wrap items-center justify-between gap-3 lg:flex">
        <div role="group" aria-label="Filter by status" class="flex flex-wrap gap-1 rounded-xl bg-[#ECEAE0] p-1">
            @foreach ($this->statusTabs() as $tab)
                @php($on = $status === $tab['value'])
                <button type="button" wire:click="$set('status', '{{ $tab['value'] }}')" aria-pressed="{{ $on ? 'true' : 'false' }}" @class([
                    'min-h-11 rounded-[9px] px-3.5 text-sm font-semibold text-ink focus-visible:outline-2 focus-visible:outline-blue',
                    'bg-white shadow-[0_1px_3px_rgba(22,24,29,0.12)]' => $on,
                ])>{{ $tab['label'] }} <span class="font-medium text-muted">{{ $this->statusCounts[$tab['value']] }}</span></button>
            @endforeach
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <label class="flex min-h-11 min-w-60 items-center gap-2 rounded-[10px] border border-line-input bg-white px-3 focus-within:outline-2 focus-within:outline-blue">
                <x-icon name="search" :size="16" class="text-muted" />
                <span class="sr-only">Search members</span>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search name, nickname, position" class="min-w-0 flex-1 bg-transparent text-sm text-ink outline-none placeholder:text-muted">
            </label>
            <label class="flex items-center gap-2 text-sm text-muted">
                Chapter
                <select wire:model.live="chapter" class="min-h-11 rounded-[10px] border border-line-input bg-white px-3 text-sm font-medium text-ink focus-visible:outline-2 focus-visible:outline-blue">
                    <option value="">All chapters</option>
                    @foreach ($this->chapterNames as $chapterName)
                        <option value="{{ $chapterName }}">{{ $chapterName }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex min-h-11 cursor-pointer items-center gap-2 text-sm text-ink">
                <input type="checkbox" wire:model.live="missingOnly" class="size-[18px] accent-blue focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                Missing info only
            </label>
        </div>
    </div>

    {{-- Desktop table + selected member panel --}}
    <div class="hidden flex-wrap items-start gap-5 lg:flex">
        <section aria-label="Member list" class="min-w-0 flex-[999_1_600px] overflow-hidden rounded-2xl border border-line bg-white">
            <div class="relative overflow-x-auto">
                <div class="min-w-[700px]">
                    <div class="grid grid-cols-[minmax(160px,2.4fr)_80px_84px_100px_124px_80px] gap-2.5 border-b border-line bg-[#FAF9F4] px-5 py-3 text-xs font-bold tracking-[0.06em] text-muted uppercase">
                        <span>Member</span><span>Position</span><span>Chapter</span><span>Birthday</span><span>Attendance</span><span>Status</span>
                    </div>
                    @foreach ($this->rows as $member)
                        @php($on = $this->selected?->is($member))
                        <button
                            type="button"
                            wire:key="row-{{ $member->id }}"
                            wire:click="select({{ $member->id }})"
                            aria-pressed="{{ $on ? 'true' : 'false' }}"
                            @class([
                                'grid min-h-15 w-full grid-cols-[minmax(160px,2.4fr)_80px_84px_100px_124px_80px] items-center gap-2.5 border-b border-line-soft px-5 py-2.5 text-left text-ink focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue',
                                'bg-[#EEF1FC] shadow-[inset_4px_0_0_var(--color-blue)]' => $on,
                                'bg-white hover:bg-[#FAF9F4]' => ! $on,
                            ])
                        >
                            <span class="flex min-w-0 items-center gap-3">
                                <x-avatar-initials :initials="$member->initials" :photo="$member->photoUrl()" :tone="$tones[$member->status->value].'-soft'" />
                                <span class="flex min-w-0 flex-col gap-0.5">
                                    <span class="truncate text-sm font-semibold">{{ $member->full_name }}</span>
                                    <span class="truncate text-xs text-muted">{{ $member->nickname ?: 'No nickname' }}{{ $member->grade_level ? ' · '.$member->grade_level : '' }}</span>
                                </span>
                            </span>
                            <span class="text-[13px] font-semibold tabular-nums">{{ $member->primaryPosition()?->code ?? '—' }}</span>
                            <span class="text-[13px]">{{ $member->chapter?->name ?? '—' }}</span>
                            <span class="text-[13px]">{{ $member->shortBirthdayLabel() ?? '—' }}</span>
                            <span class="flex items-center gap-1">
                                @foreach ($this->recentActivities as $activity)
                                    @php($mark = $this->attendanceStatus($member, $activity))
                                    <span title="{{ $activity->display_name }}: {{ $mark === App\Enums\AttendanceStatus::Present ? 'attended' : Str::lower($mark->label()) }}" @class([
                                        'rounded-full',
                                        'size-2.5 bg-blue' => $mark === App\Enums\AttendanceStatus::Present,
                                        'size-2.5 bg-[#B9B6A8]' => $mark === App\Enums\AttendanceStatus::Excused,
                                        'size-2 border border-[#A8A598]' => $mark === App\Enums\AttendanceStatus::Absent,
                                        'h-0.5 w-2.5 rounded-none bg-[#0A5F63]' => $mark === App\Enums\AttendanceStatus::Exempt,
                                    ])></span>
                                @endforeach
                                <span class="ml-1.5 text-xs text-muted">{{ $this->attendedCount($member) }}/{{ $this->expectedCount($member) }}</span>
                            </span>
                            <span><x-status-pill :status="$member->status" /></span>
                        </button>
                    @endforeach
                    @if ($this->rows->isEmpty())
                        <div class="flex flex-col items-center gap-1.5 px-5 py-12 text-center">
                            <p class="text-base font-semibold">No members match</p>
                            <p class="text-sm text-muted">Try another name, chapter or status.</p>
                        </div>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap justify-between gap-2 border-t border-line px-5 py-3 text-[13px] text-muted">
                <span>Showing {{ $this->rows->count() }} of {{ $this->statusCounts['all'] }}</span>
                <span class="flex items-center gap-3.5" aria-hidden="true">
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-blue"></span>Attended</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-[#B9B6A8]"></span>Excused</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full border border-[#A8A598]"></span>Absent</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-2.5 bg-[#0A5F63]"></span>Not required</span>
                </span>
            </div>
        </section>

        @if ($member = $this->selected)
            @php($notes = $this->notesFor($member))
            @php($position = $member->primaryPosition())
            <aside aria-label="Selected member" wire:key="selected-{{ $member->id }}" class="min-w-0 flex-[1_1_340px] overflow-hidden rounded-2xl border border-line bg-white">
                <div class="flex flex-col gap-3.5 bg-navy p-6 text-white">
                    <div class="flex items-center gap-3.5">
                        <x-avatar-initials :initials="$member->initials" :photo="$member->photoUrl()" size="xl" />
                        <div class="flex min-w-0 flex-col gap-1">
                            <p class="text-lg leading-tight font-bold">{{ $member->full_name }}</p>
                            <p class="text-[13px] text-navy-text">{{ $member->nickname ? '“'.$member->nickname.'”' : 'No nickname' }}{{ $member->chapter ? ' · '.$member->chapter->name.' chapter' : '' }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <x-status-pill :status="$member->status" />
                        <span class="rounded-full bg-navy-2 px-2.5 py-1 text-xs font-semibold text-white">{{ $position?->shortLabel() ?? 'No position' }}</span>
                    </div>
                </div>
                <div class="flex flex-col gap-[18px] px-6 py-5">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3.5">
                        <div class="flex flex-col gap-0.5"><dt class="text-xs text-muted">Chapter</dt><dd class="text-sm font-semibold">{{ $member->chapter?->name ?? '—' }}</dd></div>
                        <div class="flex flex-col gap-0.5"><dt class="text-xs text-muted">Grade level</dt><dd class="text-sm font-semibold">{{ $member->grade_level ?? '—' }}</dd></div>
                        <div class="flex flex-col gap-0.5"><dt class="text-xs text-muted">Birthday</dt><dd class="text-sm font-semibold">{{ $member->birthday_label ?? '—' }}</dd></div>
                        <div class="flex flex-col gap-0.5"><dt class="text-xs text-muted">Age</dt><dd class="text-sm font-semibold">{{ $member->age !== null ? $member->age.' years' : ($member->hasBirthday() ? 'Birth year missing' : '—') }}</dd></div>
                    </dl>

                    @if ($notes !== [])
                        <div class="flex gap-2.5 rounded-[10px] bg-[#FFF6D9] px-3.5 py-3">
                            <x-icon name="alert" class="mt-px shrink-0 text-floating-fg" />
                            <div class="flex flex-col gap-1 text-[13px] text-[#5A3A00]">
                                @isset($notes['missing'])
                                    <p><strong>Missing:</strong> {{ implode(', ', $member->missing_fields) }}</p>
                                @endisset
                                @isset($notes['mismatch'])
                                    <p><strong>Name differs on org chart:</strong> listed as “{{ $this->nameMismatches[$member->id]['name_as_listed'] }}”</p>
                                @endisset
                            </div>
                        </div>
                    @endif

                    @if ($member->remarks)
                        <div class="flex flex-col gap-1 rounded-[10px] bg-[#F3F2EC] px-3.5 py-3">
                            <p class="text-xs text-muted">Founder’s remark</p>
                            <p class="text-sm font-semibold">{{ $member->remarks }}</p>
                        </div>
                    @endif

                    <div class="flex flex-col gap-2">
                        <h2 class="text-[13px] font-bold">Attendance <span class="font-medium text-muted">· {{ $this->attendedCount($member) }}/{{ $this->expectedCount($member) }} activities</span></h2>
                        <ul>
                            @foreach ($this->recentActivities as $activity)
                                @php($mark = $this->attendanceStatus($member, $activity))
                                <li wire:key="selected-activity-{{ $activity->id }}" class="flex justify-between gap-2.5 border-b border-line-soft py-1.5 text-[13px]">
                                    <span>{{ $activity->display_name }} <span class="text-muted">· {{ $activity->date_label }}</span></span>
                                    <span @class(['font-semibold', 'text-[#2A3A9A]' => $mark === App\Enums\AttendanceStatus::Present, 'text-muted' => $mark !== App\Enums\AttendanceStatus::Present])>
                                        {{ $mark === App\Enums\AttendanceStatus::Present ? 'Attended' : $mark->label() }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="flex flex-wrap gap-2.5">
                        <a href="{{ route('members.show', $member) }}" wire:navigate class="inline-flex min-h-11 flex-auto items-center justify-center rounded-[10px] bg-blue px-4 text-sm font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">Open full profile</a>
                        @can('update', $member)
                            <a href="{{ route('members.edit', $member) }}" wire:navigate class="inline-flex min-h-11 items-center rounded-[10px] border border-line-input bg-white px-4 text-sm font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">Edit</a>
                        @endcan
                    </div>
                </div>
            </aside>
        @endif
    </div>
</div>
