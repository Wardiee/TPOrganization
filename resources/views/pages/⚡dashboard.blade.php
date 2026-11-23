<?php

use App\Enums\AttendanceStatus;
use App\Enums\DatePrecision;
use App\Enums\MemberStatus;
use App\Models\Activity;
use App\Models\Chapter;
use App\Models\HistoryEvent;
use App\Models\Member;
use App\Services\RecordChecks;
use App\Services\RecordIssue;
use App\Services\UpcomingBirthdays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    /** How many recent activities the attendance chart shows. */
    private const CHART_ACTIVITIES = 8;

    public function mount(): void
    {
        $this->authorize('viewAny', Member::class);
    }

    #[Computed]
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }

    #[Computed]
    public function greeting(): string
    {
        $hour = $this->today->hour;

        return match (true) {
            $hour < 12 => 'Good morning, Chairman',
            $hour < 18 => 'Good afternoon, Chairman',
            default => 'Good evening, Chairman',
        };
    }

    /**
     * @return array{total: int, active: int, floating: int, inactive: int}
     */
    #[Computed]
    public function memberCounts(): array
    {
        $byStatus = Member::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[MemberStatus::Active->value] ?? 0),
            'floating' => (int) ($byStatus[MemberStatus::Floating->value] ?? 0),
            'inactive' => (int) ($byStatus[MemberStatus::Inactive->value] ?? 0),
        ];
    }

    /**
     * Recent activities in recorded order with their present and excused counts.
     *
     * @return Collection<int, Activity>
     */
    #[Computed]
    public function chartActivities(): Collection
    {
        return Activity::query()
            ->withCount([
                'attendances as present_count' => fn ($query) => $query->where('status', AttendanceStatus::Present),
                'attendances as excused_count' => fn ($query) => $query->where('status', AttendanceStatus::Excused),
            ])
            ->orderByDesc('id')
            ->limit(self::CHART_ACTIVITIES)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * The most recent dated activity (or the last recorded one if none have dates).
     */
    #[Computed]
    public function lastActivity(): ?Activity
    {
        $latestId = Activity::query()->whereNotNull('starts_on')->orderByDesc('starts_on')->orderByDesc('id')->value('id')
            ?? Activity::query()->max('id');

        return $latestId === null ? null : Activity::query()
            ->withCount(['attendances as present_count' => fn ($query) => $query->where('status', AttendanceStatus::Present)])
            ->find($latestId);
    }

    #[Computed]
    public function birthdaysSoon(): Collection
    {
        return app(UpcomingBirthdays::class)->from($this->today, withinDays: 30);
    }

    #[Computed]
    public function nextBirthdays(): Collection
    {
        return app(UpcomingBirthdays::class)->from($this->today)->take(4);
    }

    /**
     * @return array{complete: int, total: int, percent: int}
     */
    #[Computed]
    public function profiles(): array
    {
        $members = Member::query()->with('currentAssignments')->get();
        $complete = $members->filter(fn (Member $member) => $member->missing_fields === [])->count();
        $total = $members->count();

        return ['complete' => $complete, 'total' => $total, 'percent' => $total > 0 ? (int) round($complete / $total * 100) : 0];
    }

    /**
     * @return list<RecordIssue>
     */
    #[Computed]
    public function issues(): array
    {
        return app(RecordChecks::class)->issues();
    }

    /**
     * @return Collection<int, Chapter>
     */
    #[Computed]
    public function chapters(): Collection
    {
        return Chapter::query()->withCount('members')->orderByDesc('members_count')->orderBy('id')->get();
    }

    /**
     * @return Collection<int, HistoryEvent>
     */
    #[Computed]
    public function recentChanges(): Collection
    {
        return HistoryEvent::query()->latestFirst()->limit(4)->get();
    }

    public function historyDate(HistoryEvent $event): string
    {
        return $event->date_precision === DatePrecision::Year ? $event->happened_on->format('Y') : $event->happened_on->format('M j, Y');
    }

    /**
     * Short label under a bar on phones: "Leiana", "Oct 6", "No date".
     */
    public function shortActivityLabel(Activity $activity): string
    {
        if (filled($activity->name)) {
            return Str::before($activity->name, ' ');
        }

        return $activity->starts_on?->format('M j') ?? 'No date';
    }

    /**
     * "Tope · TROP-15 · birth year missing" / "Jan² · TROPS-2 · turns 19".
     *
     * @param  array{member: Member, turns: int|null}  $birthday
     */
    public function birthdayDetail(array $birthday): string
    {
        $member = $birthday['member'];

        return collect([
            $member->nickname,
            $member->primaryPosition()?->code,
            $birthday['turns'] === null ? 'birth year missing' : 'turns '.$birthday['turns'],
        ])->filter()->implode(' · ');
    }

    public function inDays(int $days): string
    {
        return match ($days) {
            0 => 'today',
            1 => 'tomorrow',
            default => "in {$days} days",
        };
    }

    public function excusedNote(): ?string
    {
        $notes = $this->chartActivities
            ->where('excused_count', '>', 0)
            ->map(fn (Activity $activity) => "{$activity->display_name} also had {$activity->excused_count} marked excused.");

        return $notes->isEmpty() ? null : $notes->implode(' ');
    }
};
?>

@php
    $counts = $this->memberCounts;
    $last = $this->lastActivity;
    $soon = $this->birthdaysSoon;
    $next = $soon->first();
    $profiles = $this->profiles;
    $maxPresent = max(1, (int) $this->chartActivities->max('present_count'));
    $maxChapter = max(1, (int) $this->chapters->max('members_count'));
    $tagTones = [
        App\Services\RecordIssue::TONE_ALERT => 'bg-alert-bg text-alert-fg',
        App\Services\RecordIssue::TONE_INFO => 'bg-info-bg text-info-fg',
        App\Services\RecordIssue::TONE_MISSING => 'bg-floating-bg text-floating-fg',
    ];
    $nextLabel = $next
        ? 'Next: '.Str::before($next['member']->full_name, ' ').($next['member']->nickname ? ' ('.$next['member']->nickname.')' : '').' · '.$next['date']->format('M j')
        : 'None in the next 30 days';
    $nextLabelShort = $next
        ? 'Next: '.($next['member']->nickname ?: Str::before($next['member']->full_name, ' ')).' · '.$next['date']->format('M j')
        : 'None in the next 30 days';
@endphp

{{-- Designs: docs/design/web/Main.dc.html (lg+), docs/design/mobile/Home.dc.html (phones) --}}
<div class="contents">
    {{-- ───────────── Phone ───────────── --}}
    <x-mobile-top-bar />

    <section aria-label="Greeting" class="-mx-4 -mt-4 flex flex-col gap-4 rounded-b-3xl bg-navy px-4 pt-1 pb-5 lg:hidden">
        <div class="flex flex-col gap-1">
            <p class="text-[13px] text-navy-muted">{{ $this->today->format('l, F j') }}</p>
            <h1 class="font-display text-[26px] font-extrabold tracking-[-0.01em] text-white">{{ $this->greeting }}</h1>
        </div>
        <div class="grid grid-cols-3 gap-2">
            <a href="{{ route('activities') }}" wire:navigate class="flex min-h-18 flex-col items-center justify-center gap-1.5 rounded-[14px] bg-yellow text-center text-xs font-bold text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow">
                <x-icon name="check-square" :size="22" />Take attendance
            </a>
            <a href="{{ route('members.create') }}" wire:navigate class="flex min-h-18 flex-col items-center justify-center gap-1.5 rounded-[14px] bg-navy-2 text-center text-xs font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow">
                <x-icon name="user-plus" :size="22" />Add member
            </a>
            <a href="{{ route('activities') }}" wire:navigate class="flex min-h-18 flex-col items-center justify-center gap-1.5 rounded-[14px] bg-navy-2 text-center text-xs font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow">
                <x-icon name="calendar-plus" :size="22" />New activity
            </a>
        </div>
    </section>

    <section aria-label="Summary" class="grid grid-cols-2 gap-2.5 lg:hidden">
        <a href="{{ route('members.index') }}" wire:navigate class="flex flex-col gap-1.5 rounded-2xl border border-line bg-white p-3.5 focus-visible:outline-2 focus-visible:outline-blue">
            <span class="text-[11px] font-bold tracking-[0.06em] text-muted uppercase">Members</span>
            <span class="font-display text-[32px] leading-none font-extrabold">{{ $counts['total'] }}</span>
            <span class="text-xs font-semibold text-active-fg">{{ $counts['active'] }} active · <span class="text-floating-fg">{{ $counts['floating'] }} floating</span></span>
        </a>
        <a href="{{ route('activities') }}" wire:navigate class="flex flex-col gap-1.5 rounded-2xl border border-line bg-white p-3.5 focus-visible:outline-2 focus-visible:outline-blue">
            <span class="text-[11px] font-bold tracking-[0.06em] text-muted uppercase">Last turnout</span>
            <span class="font-display text-[32px] leading-none font-extrabold">{{ $last?->present_count ?? 0 }}<span class="text-base text-muted"> / {{ $counts['active'] }}</span></span>
            <span class="truncate text-xs text-muted">{{ $last?->display_name ?? 'No activities yet' }}</span>
        </a>
        <a href="{{ route('birthdays') }}" wire:navigate class="flex flex-col gap-1.5 rounded-2xl bg-navy p-3.5 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
            <span class="text-[11px] font-bold tracking-[0.06em] text-yellow uppercase">Birthdays · 30 days</span>
            <span class="font-display text-[32px] leading-none font-extrabold">{{ $soon->count() }}</span>
            <span class="truncate text-xs text-navy-text">{{ $nextLabelShort }}</span>
        </a>
        <a href="{{ route('members.index', ['missing' => 1]) }}" wire:navigate class="flex flex-col gap-1.5 rounded-2xl border border-line bg-white p-3.5 focus-visible:outline-2 focus-visible:outline-blue">
            <span class="text-[11px] font-bold tracking-[0.06em] text-muted uppercase">Profiles complete</span>
            <span class="font-display text-[32px] leading-none font-extrabold">{{ $profiles['complete'] }}<span class="text-base text-muted"> / {{ $profiles['total'] }}</span></span>
            <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-inactive-bg" aria-hidden="true"><span class="block h-1.5 rounded-full bg-blue" style="width: {{ $profiles['percent'] }}%"></span></span>
        </a>
    </section>

    <x-card class="flex flex-col gap-1 p-4 lg:hidden">
        <div class="flex items-baseline justify-between gap-2">
            <h2 class="text-base font-bold">Needs your attention</h2>
            <a href="{{ route('tasks') }}" wire:navigate class="text-[13px] font-semibold text-blue hover:text-navy">All tasks</a>
        </div>
        @if ($this->issues === [])
            <p class="py-3 text-sm text-muted">Nothing needs attention right now.</p>
        @else
            <ul>
                @foreach ($this->issues as $issue)
                    <li wire:key="phone-issue-{{ $issue->key }}" class="border-b border-line-soft last:border-b-0">
                        <a href="{{ $issue->url }}" wire:navigate class="flex min-h-14 items-center gap-3 py-2.5 focus-visible:outline-2 focus-visible:outline-blue">
                            <span class="shrink-0 rounded-md px-[7px] py-1 text-[10px] font-bold tracking-[0.06em] {{ $tagTones[$issue->tone] }}">{{ $issue->tag }}</span>
                            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span class="text-sm font-semibold">{{ $issue->title }}</span>
                                <span class="line-clamp-2 text-xs text-muted">{{ $issue->detail }}</span>
                            </span>
                            <x-icon name="chevron-right" class="shrink-0 text-[#8A8D99]" />
                            <span class="sr-only">{{ $issue->actionLabel }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <section aria-labelledby="phone-birthdays-heading" class="flex flex-col gap-2.5 lg:hidden">
        <div class="flex items-baseline justify-between gap-2">
            <h2 id="phone-birthdays-heading" class="text-base font-bold">Upcoming birthdays</h2>
            <a href="{{ route('birthdays') }}" wire:navigate class="text-[13px] font-semibold text-blue hover:text-navy">Calendar</a>
        </div>
        <div class="-mx-4 flex gap-2.5 overflow-x-auto px-4 pb-1">
            @forelse ($this->nextBirthdays as $birthday)
                <a href="{{ route('members.show', $birthday['member']) }}" wire:navigate wire:key="phone-birthday-{{ $birthday['member']->id }}" class="flex w-[150px] shrink-0 flex-col gap-2 rounded-2xl border border-line bg-white p-3.5 focus-visible:outline-2 focus-visible:outline-blue">
                    <span class="flex items-center justify-between">
                        <x-date-badge :date="$birthday['date']" size="sm" />
                        <span class="text-[11px] font-semibold text-muted">{{ $this->inDays($birthday['days']) }}</span>
                    </span>
                    <span class="text-sm font-semibold">{{ $birthday['member']->full_name }}</span>
                    <span class="text-xs text-muted">{{ $this->birthdayDetail($birthday) }}</span>
                </a>
            @empty
                <p class="text-sm text-muted">No birthdays recorded yet.</p>
            @endforelse
        </div>
    </section>

    <x-card class="flex flex-col gap-3.5 p-4 lg:hidden">
        <div class="flex items-baseline justify-between gap-2">
            <h2 class="text-base font-bold">Attendance per activity</h2>
            <a href="{{ route('activities') }}" wire:navigate class="text-[13px] font-semibold text-blue hover:text-navy">View all</a>
        </div>
        <div class="flex h-35 items-end gap-2.5 border-b border-line" role="img" aria-label="Members present per activity: {{ $this->chartActivities->map(fn ($activity) => $activity->display_name.' '.$activity->date_label.', '.$activity->present_count)->implode('; ') }}">
            @foreach ($this->chartActivities as $activity)
                <div wire:key="phone-bar-{{ $activity->id }}" class="flex h-full flex-1 flex-col items-center justify-end gap-1.5" aria-hidden="true">
                    <span class="text-xs font-bold">{{ $activity->present_count }}</span>
                    <span @class(['block w-full rounded-t-md', 'bg-[#F5C518]' => $loop->last, 'bg-blue' => ! $loop->last]) style="height: {{ round($activity->present_count / $maxPresent * 100) }}px"></span>
                </div>
            @endforeach
        </div>
        <div class="flex gap-2.5" aria-hidden="true">
            @foreach ($this->chartActivities as $activity)
                <span wire:key="phone-bar-label-{{ $activity->id }}" class="flex-1 truncate text-center text-[11px] font-semibold text-muted">{{ $this->shortActivityLabel($activity) }}</span>
            @endforeach
        </div>
    </x-card>

    {{-- ───────────── Desktop ───────────── --}}
    <header class="hidden flex-wrap items-end justify-between gap-4 lg:flex">
        <div class="flex flex-col gap-1.5">
            <p class="text-sm text-muted">{{ $this->today->format('l, F j, Y') }}</p>
            <h1 class="font-display text-[32px] font-extrabold tracking-[-0.01em]">{{ $this->greeting }}</h1>
        </div>
        <div class="flex flex-wrap gap-2.5">
            <a href="{{ route('activities') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-[10px] border border-line-input bg-white px-4 text-sm font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                <x-icon name="check-square" :size="16" />Take attendance
            </a>
            <a href="{{ route('activities') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-[10px] border border-line-input bg-white px-4 text-sm font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                <x-icon name="calendar-plus" :size="16" />New activity
            </a>
            <a href="{{ route('members.create') }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-[10px] bg-blue px-[18px] text-sm font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                <x-icon name="user-plus" :size="16" />Add member
            </a>
        </div>
    </header>

    <section aria-label="Summary" class="hidden flex-wrap gap-4 lg:flex">
        <a href="{{ route('members.index') }}" wire:navigate class="flex flex-[1_1_230px] flex-col gap-2.5 rounded-2xl border border-line bg-white p-5 hover:border-line-input focus-visible:outline-2 focus-visible:outline-blue">
            <span class="text-[13px] font-semibold tracking-[0.06em] text-muted uppercase">Members</span>
            <span class="font-display text-[40px] leading-none font-extrabold">{{ $counts['total'] }}</span>
            <span class="flex flex-wrap gap-1.5">
                <span class="rounded-full bg-active-bg px-2 py-1 text-xs font-semibold text-active-fg">{{ $counts['active'] }} active</span>
                <span class="rounded-full bg-floating-bg px-2 py-1 text-xs font-semibold text-floating-fg">{{ $counts['floating'] }} floating</span>
                <span class="rounded-full bg-inactive-bg px-2 py-1 text-xs font-semibold text-inactive-fg">{{ $counts['inactive'] }} inactive</span>
            </span>
        </a>
        <a href="{{ route('activities') }}" wire:navigate class="flex flex-[1_1_230px] flex-col gap-2.5 rounded-2xl border border-line bg-white p-5 hover:border-line-input focus-visible:outline-2 focus-visible:outline-blue">
            <span class="text-[13px] font-semibold tracking-[0.06em] text-muted uppercase">Last activity turnout</span>
            <span class="font-display text-[40px] leading-none font-extrabold">{{ $last?->present_count ?? 0 }}<span class="text-xl text-muted"> / {{ $counts['active'] }} active</span></span>
            <span class="text-[13px] text-muted">{{ $last ? $last->display_name.' · '.$last->date_label : 'No activities yet' }}</span>
        </a>
        <a href="#upcoming-birthdays" class="flex flex-[1_1_230px] flex-col gap-2.5 rounded-2xl bg-navy p-5 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
            <span class="text-[13px] font-semibold tracking-[0.06em] text-yellow uppercase">Birthdays · next 30 days</span>
            <span class="font-display text-[40px] leading-none font-extrabold">{{ $soon->count() }}</span>
            <span class="text-[13px] text-navy-text">{{ $nextLabel }}</span>
        </a>
        <a href="{{ route('members.index', ['missing' => 1]) }}" wire:navigate class="flex flex-[1_1_230px] flex-col gap-2.5 rounded-2xl border border-line bg-white p-5 hover:border-line-input focus-visible:outline-2 focus-visible:outline-blue">
            <span class="text-[13px] font-semibold tracking-[0.06em] text-muted uppercase">Profiles missing info</span>
            <span class="font-display text-[40px] leading-none font-extrabold">{{ $profiles['total'] - $profiles['complete'] }}<span class="text-xl text-muted"> / {{ $profiles['total'] }}</span></span>
            <span class="block h-2 overflow-hidden rounded-full bg-inactive-bg" aria-hidden="true"><span class="block h-2 rounded-full bg-blue" style="width: {{ $profiles['percent'] }}%"></span></span>
            <span class="text-[13px] text-muted">{{ $profiles['complete'] }} complete ({{ $profiles['percent'] }}%)</span>
        </a>
    </section>

    <div class="hidden flex-wrap gap-4 lg:flex">
        <x-card class="flex min-w-0 flex-[2_1_520px] flex-col gap-5 p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="text-lg font-bold">Attendance per activity</h2>
                <a href="{{ route('activities') }}" wire:navigate class="text-sm font-semibold text-blue hover:text-navy">View all activities</a>
            </div>
            <div class="overflow-x-auto">
                <div class="flex h-60 min-w-[520px] items-end gap-4 border-b border-line px-1" role="img" aria-label="Members present per activity: {{ $this->chartActivities->map(fn ($activity) => $activity->display_name.' '.$activity->date_label.', '.$activity->present_count)->implode('; ') }}">
                    @foreach ($this->chartActivities as $activity)
                        <div wire:key="bar-{{ $activity->id }}" class="flex h-full flex-1 flex-col items-center justify-end gap-2" aria-hidden="true">
                            <span class="text-sm font-bold">{{ $activity->present_count }}</span>
                            <span @class(['block w-full max-w-16 rounded-t-lg', 'bg-[#F5C518]' => $loop->last, 'bg-blue' => ! $loop->last]) style="height: {{ round($activity->present_count / $maxPresent * 190) }}px"></span>
                        </div>
                    @endforeach
                </div>
                <div class="flex min-w-[520px] gap-4 px-1 pt-2.5" aria-hidden="true">
                    @foreach ($this->chartActivities as $activity)
                        <div wire:key="bar-label-{{ $activity->id }}" class="flex flex-1 flex-col gap-0.5 text-center">
                            <span class="text-xs font-semibold">{{ $activity->display_name }}</span>
                            <span class="text-xs text-muted">{{ $activity->date_label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <p class="text-[13px] text-muted">Number of members who attended.{{ $this->excusedNote() ? ' '.$this->excusedNote() : '' }}</p>
        </x-card>

        <x-card id="upcoming-birthdays" class="flex min-w-0 flex-[1_1_300px] flex-col gap-4 p-6">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="text-lg font-bold">Upcoming birthdays</h2>
                <a href="{{ route('birthdays') }}" wire:navigate class="text-sm font-semibold text-blue hover:text-navy">Calendar</a>
            </div>
            <ul class="flex flex-col gap-1">
                @forelse ($this->nextBirthdays as $birthday)
                    <li wire:key="birthday-{{ $birthday['member']->id }}" class="border-b border-line-soft">
                        <a href="{{ route('members.show', $birthday['member']) }}" wire:navigate class="flex items-center gap-3 py-2.5 focus-visible:outline-2 focus-visible:outline-blue">
                            <x-date-badge :date="$birthday['date']" />
                            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span class="text-sm font-semibold">{{ $birthday['member']->full_name }}</span>
                                <span class="text-[13px] text-muted">{{ $this->birthdayDetail($birthday) }}</span>
                            </span>
                            <span class="text-xs font-semibold whitespace-nowrap text-muted">{{ $this->inDays($birthday['days']) }}</span>
                        </a>
                    </li>
                @empty
                    <li class="py-2.5 text-sm text-muted">No birthdays recorded yet.</li>
                @endforelse
            </ul>
        </x-card>
    </div>

    <div class="hidden flex-wrap gap-4 lg:flex">
        <x-card class="flex min-w-0 flex-[2_1_520px] flex-col gap-3 p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="text-lg font-bold">Needs your attention</h2>
                <p class="text-[13px] text-muted">Found automatically in your records</p>
            </div>
            @if ($this->issues === [])
                <p class="py-3.5 text-sm text-muted">Nothing needs attention right now.</p>
            @else
                <ul>
                    @foreach ($this->issues as $issue)
                        <li wire:key="issue-{{ $issue->key }}" class="flex items-start gap-3.5 border-b border-line-soft py-3.5">
                            <x-tag :class="$tagTones[$issue->tone]">{{ $issue->tag }}</x-tag>
                            <span class="flex min-w-0 flex-1 flex-col gap-[3px]">
                                <span class="text-sm font-semibold">{{ $issue->title }}</span>
                                <span class="text-[13px] text-muted">{{ $issue->detail }}</span>
                            </span>
                            <a href="{{ $issue->url }}" wire:navigate class="inline-flex min-h-11 shrink-0 items-center text-sm font-semibold whitespace-nowrap text-blue hover:text-navy lg:min-h-0" aria-label="{{ $issue->actionLabel }}: {{ $issue->title }}">{{ $issue->actionLabel }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <div class="flex min-w-0 flex-[1_1_300px] flex-col gap-4">
            <x-card class="flex flex-col gap-3.5 p-6">
                <h2 class="text-lg font-bold">Members by chapter</h2>
                @foreach ($this->chapters as $chapter)
                    <div wire:key="chapter-{{ $chapter->id }}" class="flex flex-col gap-1.5">
                        <span class="flex justify-between text-sm"><span class="font-semibold">{{ $chapter->name }}</span><span class="text-muted">{{ $chapter->members_count }}</span></span>
                        <span class="block h-2 rounded-full bg-line-soft" aria-hidden="true"><span class="block h-2 rounded-full bg-blue" style="width: {{ round($chapter->members_count / $maxChapter * 100) }}%"></span></span>
                    </div>
                @endforeach
            </x-card>

            <x-card class="flex flex-col gap-3 p-6">
                <h2 class="text-lg font-bold">Recent changes</h2>
                @forelse ($this->recentChanges as $event)
                    <div wire:key="change-{{ $event->id }}" class="flex gap-3">
                        <span class="mt-1.5 box-content size-2 shrink-0 rounded-full border-2 border-navy bg-yellow" aria-hidden="true"></span>
                        <span class="flex flex-col gap-0.5">
                            <span class="text-sm font-semibold">{{ $event->title }}</span>
                            <span class="text-xs text-muted">{{ $this->historyDate($event) }}</span>
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-muted">No changes recorded yet.</p>
                @endforelse
            </x-card>
        </div>
    </div>
</div>
