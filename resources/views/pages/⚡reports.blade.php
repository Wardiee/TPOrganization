<?php

use App\Enums\Branch;
use App\Enums\MemberStatus;
use App\Models\Activity;
use App\Models\Chapter;
use App\Models\Member;
use App\Models\Position;
use App\Services\AttendanceReport;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Reports')] class extends Component
{
    /** "all" or a year with dated activities. */
    #[Url(except: 'all')]
    public string $period = 'all';

    /** Which members the attendance list shows: a status value or "all". */
    #[Url(as: 'members', except: 'active')]
    public string $memberStatus = 'active';

    /** "lowest" (who needs follow-up) or "highest" first. */
    #[Url(except: 'lowest')]
    public string $order = 'lowest';

    public function mount(): void
    {
        $this->authorize('viewAny', Member::class);
        $this->authorize('viewAny', Activity::class);
    }

    /**
     * @return list<int>
     */
    #[Computed]
    public function years(): array
    {
        return AttendanceReport::years();
    }

    #[Computed]
    public function year(): ?int
    {
        return in_array((int) $this->period, $this->years, true) ? (int) $this->period : null;
    }

    #[Computed]
    public function report(): AttendanceReport
    {
        return AttendanceReport::forYear($this->year);
    }

    /**
     * @return list<array{member: Member, attended: int, excused: int, required: int, rate: float|null}>
     */
    #[Computed]
    public function memberRows(): array
    {
        return $this->report->memberRows(MemberStatus::tryFrom($this->memberStatus), $this->order !== 'highest');
    }

    /**
     * Active members who attended fewer than half of the activities they were required at.
     */
    #[Computed]
    public function lowAttendance(): int
    {
        return collect($this->report->memberRows(MemberStatus::Active))
            ->filter(fn (array $row) => $row['rate'] !== null && $row['rate'] < 0.5)
            ->count();
    }

    /**
     * @return array{active: int, floating: int, inactive: int, total: int}
     */
    #[Computed]
    public function statusCounts(): array
    {
        $byStatus = Member::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = collect(MemberStatus::cases())->mapWithKeys(fn (MemberStatus $status) => [$status->value => (int) ($byStatus[$status->value] ?? 0)]);

        return [...$counts->all(), 'total' => $counts->sum()];
    }

    /**
     * Seats filled per branch, plus active members without a seat.
     *
     * @return array{branches: list<array{branch: Branch, filled: int, seats: int}>, unassigned: int}
     */
    #[Computed]
    public function seats(): array
    {
        $positions = Position::query()->whereNotNull('branch')->with('currentAssignment')->get();

        return [
            'branches' => collect(Branch::cases())->map(fn (Branch $branch) => [
                'branch' => $branch,
                'filled' => $positions->where('branch', $branch)->filter(fn (Position $position) => $position->currentAssignment !== null)->count(),
                'seats' => $positions->where('branch', $branch)->count(),
            ])->all(),
            'unassigned' => Member::active()->whereDoesntHave('currentAssignments')->count(),
        ];
    }

    /**
     * @return Collection<int, Chapter>
     */
    #[Computed]
    public function chapters(): Collection
    {
        return Chapter::query()->withCount('members')->orderByDesc('members_count')->orderBy('id')->get();
    }

    public static function percent(?float $rate): string
    {
        return $rate === null ? '—' : round($rate * 100).'%';
    }
};
?>

@php
    $summary = $this->report->summary();
    $activityRows = $this->report->activityRows();
    $counts = $this->statusCounts;
    $periodLabel = $this->year ? (string) $this->year : 'All time';
    $downloadQuery = $this->year ? ['year' => $this->year] : [];
    $maxChapter = max(1, (int) $this->chapters->max('members_count'));
    $segment = 'min-h-11 rounded-[9px] px-3 text-sm font-semibold text-ink focus-visible:outline-2 focus-visible:outline-blue';
    $segmentOn = 'bg-white shadow-[0_1px_3px_rgba(22,24,29,0.12)]';
    $tile = 'flex min-w-0 flex-col gap-1.5 rounded-2xl border border-line bg-white p-4 lg:gap-2.5 lg:p-5';
    $tileLabel = 'text-[11px] font-bold tracking-[0.06em] text-muted uppercase lg:text-[13px] lg:font-semibold';
    $tileNumber = 'font-display text-[30px] leading-none font-extrabold lg:text-[40px]';
    // Attendance colors (validated for colour-blind separation): present = brand blue, excused = gold, absent = rust.
    $legend = [['Present', 'bg-blue'], ['Excused', 'bg-[#C99A1E]'], ['Absent', 'bg-absent']];
@endphp

<div class="contents">
    <x-page-header title="Reports" subtitle="Attendance and membership" back-to-more>
        <label class="flex items-center gap-2 text-sm font-semibold text-muted">
            Period
            <select wire:model.live="period" class="min-h-11 rounded-[10px] border border-line-input bg-white px-3 text-[15px] font-semibold text-ink focus:outline-2 focus:outline-blue lg:text-sm">
                <option value="all">All time</option>
                @foreach ($this->years as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </label>

        <div class="relative" x-data="{ open: false }" x-on:keydown.escape="open = false" x-on:click.outside="open = false">
            <button type="button" x-on:click="open = ! open" aria-haspopup="true" x-bind:aria-expanded="open" class="inline-flex min-h-11 items-center gap-2 rounded-[10px] bg-blue px-4 text-sm font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                <x-icon name="download" :size="16" />
                Download
            </button>
            <div x-show="open" x-cloak class="tp-pop absolute right-0 z-20 mt-2 flex w-72 flex-col rounded-xl border border-line bg-white p-1 shadow-[0_8px_24px_rgba(22,24,29,0.18)]">
                <a href="{{ route('reports.members', $downloadQuery) }}" x-on:click="open = false" class="flex min-h-11 flex-col justify-center rounded-lg px-3 py-2 hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue">
                    <span class="text-sm font-semibold">Member list</span>
                    <span class="text-xs text-muted">Details and attendance · {{ $periodLabel }} · opens in Excel</span>
                </a>
                <a href="{{ route('reports.attendance', $downloadQuery) }}" x-on:click="open = false" class="flex min-h-11 flex-col justify-center rounded-lg px-3 py-2 hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue">
                    <span class="text-sm font-semibold">Attendance sheet</span>
                    <span class="text-xs text-muted">Every member × activity · {{ $periodLabel }}</span>
                </a>
            </div>
        </div>
    </x-page-header>

    {{-- Summary --}}
    <section aria-label="Summary for {{ $periodLabel }}" class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        <div class="{{ $tile }}">
            <span class="{{ $tileLabel }}">Average turnout</span>
            <span data-count-up class="{{ $tileNumber }}">{{ $this->percent($summary['average_turnout']) }}</span>
            <span class="text-xs text-muted lg:text-[13px]">Present ÷ expected · {{ $periodLabel }}</span>
        </div>
        <div class="{{ $tile }}">
            <span class="{{ $tileLabel }}">Activities</span>
            <span data-count-up class="{{ $tileNumber }}">{{ $summary['activities'] }}</span>
            <span class="truncate text-xs text-muted lg:text-[13px]">
                {{ $summary['best'] ? 'Best: '.$summary['best']['activity']->display_name.' ('.$this->percent($summary['best']['turnout']).')' : 'None recorded' }}
            </span>
        </div>
        <div class="{{ $tile }}">
            <span class="{{ $tileLabel }}">Members</span>
            <span data-count-up class="{{ $tileNumber }}">{{ $counts['total'] }}</span>
            <span class="flex flex-wrap gap-1">
                <span class="rounded-full bg-active-bg px-2 py-0.5 text-xs font-semibold text-active-fg">{{ $counts['active'] }} active</span>
                <span class="rounded-full bg-floating-bg px-2 py-0.5 text-xs font-semibold text-floating-fg">{{ $counts['floating'] }} floating</span>
                <span class="rounded-full bg-inactive-bg px-2 py-0.5 text-xs font-semibold text-inactive-fg">{{ $counts['inactive'] }} inactive</span>
            </span>
        </div>
        <a href="#member-attendance" wire:click="$set('order', 'lowest')" class="{{ $tile }} tp-navy tp-lift bg-navy! text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
            <span class="{{ $tileLabel }} text-yellow!">Below 50% attendance</span>
            <span data-count-up class="{{ $tileNumber }}">{{ $this->lowAttendance }}</span>
            <span class="text-xs text-navy-text lg:text-[13px]">Active members · {{ $periodLabel }}</span>
        </a>
    </section>

    {{-- Turnout by activity --}}
    <x-card class="flex min-w-0 flex-col gap-4 p-4 lg:gap-5 lg:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-[15px] font-bold lg:text-lg">Turnout by activity</h2>
            <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold text-muted" aria-label="Legend">
                @foreach ($legend as [$label, $color])
                    <li class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm {{ $color }}" aria-hidden="true"></span>{{ $label }}</li>
                @endforeach
            </ul>
        </div>

        @if ($activityRows === [])
            <p class="text-sm text-muted">No activities recorded {{ $this->year ? 'in '.$this->year : 'yet' }}.</p>
        @else
            <ul class="flex flex-col">
                @foreach ($activityRows as $row)
                    @php
                        $base = max(1, $row['expected'], $row['present'] + $row['excused']);
                        $segments = [
                            ['Present', $row['present'], 'bg-blue'],
                            ['Excused', $row['excused'], 'bg-[#C99A1E]'],
                            ['Absent', $row['absent'], 'bg-absent'],
                        ];
                    @endphp
                    <li wire:key="activity-{{ $row['activity']->id }}" class="flex flex-col gap-2 border-b border-line-soft py-3 last:border-b-0 lg:flex-row lg:items-center lg:gap-5">
                        <div class="flex min-w-0 items-baseline justify-between gap-3 lg:w-64 lg:shrink-0 lg:flex-col lg:items-start lg:gap-0.5">
                            <span class="flex min-w-0 flex-col gap-0.5">
                                <span class="truncate text-sm font-semibold">{{ $row['activity']->display_name }}</span>
                                <span class="text-xs text-muted">{{ $row['activity']->date_label }}</span>
                            </span>
                            <span class="shrink-0 text-sm font-bold tabular-nums lg:hidden">{{ $row['present'] }} / {{ $row['expected'] }} · {{ $this->percent($row['turnout']) }}</span>
                        </div>
                        <div class="tp-bar-x flex h-3 min-w-0 shrink-0 gap-0.5 lg:flex-1" aria-hidden="true">
                            @foreach ($segments as [$label, $value, $color])
                                @if ($value > 0)
                                    <span class="tp-bar h-full rounded-[3px] {{ $color }}" style="width: {{ $value / $base * 100 }}%" title="{{ $label }}: {{ $value }}"></span>
                                @endif
                            @endforeach
                        </div>
                        <p class="text-xs text-muted lg:w-56 lg:shrink-0 lg:text-right">
                            <span class="hidden text-sm font-bold text-ink tabular-nums lg:inline">{{ $row['present'] }} / {{ $row['expected'] }} · {{ $this->percent($row['turnout']) }}<br></span>
                            {{ $row['present'] }} present · {{ $row['excused'] }} excused · {{ $row['absent'] }} absent
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="text-[13px] text-muted">Expected is today’s active members, minus anyone marked “not required” for that activity.</p>
    </x-card>

    <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-start lg:gap-5">
        {{-- Member attendance --}}
        <x-card id="member-attendance" class="flex min-w-0 scroll-mt-20 flex-col gap-3 p-4 lg:flex-[2_1_560px] lg:gap-4 lg:p-6">
            <div class="flex flex-col gap-0.5">
                <h2 class="text-[15px] font-bold lg:text-lg">Member attendance</h2>
                <p class="text-[13px] text-muted">Activities attended out of those each member was required at · {{ $periodLabel }}</p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <div role="group" aria-label="Members to show" class="flex flex-wrap gap-1 rounded-xl bg-[#ECEAE0] p-1">
                    @foreach (['active' => 'Active', 'floating' => 'Floating', 'inactive' => 'Inactive', 'all' => 'All'] as $value => $label)
                        <button type="button" wire:click="$set('memberStatus', '{{ $value }}')" aria-pressed="{{ $memberStatus === $value ? 'true' : 'false' }}" @class([$segment, $segmentOn => $memberStatus === $value])>{{ $label }}</button>
                    @endforeach
                </div>
                <div role="group" aria-label="Order" class="flex gap-1 rounded-xl bg-[#ECEAE0] p-1">
                    @foreach (['lowest' => 'Lowest first', 'highest' => 'Highest first'] as $value => $label)
                        <button type="button" wire:click="$set('order', '{{ $value }}')" aria-pressed="{{ $order === $value ? 'true' : 'false' }}" @class([$segment, $segmentOn => $order === $value])>{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <ul class="flex flex-col">
                @forelse ($this->memberRows as $row)
                    @php($member = $row['member'])
                    <li wire:key="member-{{ $member->id }}" class="border-b border-line-soft last:border-b-0">
                        <a href="{{ route('members.show', $member) }}" wire:navigate class="flex min-h-15 items-center gap-3 py-2 hover:bg-[#FAF9F4] focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue">
                            <x-avatar-initials :initials="$member->initials" :photo="$member->photoUrl()" size="sm" tone="active-soft" />
                            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span class="truncate text-sm font-semibold">{{ $member->full_name }}</span>
                                <span class="truncate text-xs text-muted">
                                    {{ collect([$member->primaryPosition()?->code ?? 'No position', $member->chapter?->name, $row['excused'] > 0 ? $row['excused'].' excused' : null])->filter()->implode(' · ') }}
                                </span>
                            </span>
                            <span class="hidden h-2 w-28 shrink-0 overflow-hidden rounded-full bg-inactive-bg sm:block" aria-hidden="true">
                                <span class="tp-bar tp-bar-x block h-full rounded-full bg-blue" style="width: {{ ($row['rate'] ?? 0) * 100 }}%"></span>
                            </span>
                            <span class="flex w-20 shrink-0 flex-col items-end gap-0.5">
                                <span @class(['text-sm font-bold tabular-nums', 'text-absent' => $row['rate'] !== null && $row['rate'] < 0.5])>{{ $this->percent($row['rate']) }}</span>
                                <span class="text-xs text-muted tabular-nums">{{ $row['required'] > 0 ? $row['attended'].' of '.$row['required'] : 'Not required' }}</span>
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="py-3 text-sm text-muted">No {{ $memberStatus === 'all' ? '' : $memberStatus.' ' }}members.</li>
                @endforelse
            </ul>
        </x-card>

        {{-- Membership today --}}
        <x-card class="flex min-w-0 flex-col gap-5 p-4 lg:flex-[1_1_300px] lg:p-6">
            <div class="flex flex-col gap-0.5">
                <h2 class="text-[15px] font-bold lg:text-lg">Membership today</h2>
                <p class="text-[13px] text-muted">Not affected by the period.</p>
            </div>

            <div class="flex flex-col gap-3">
                <h3 class="text-xs font-bold tracking-[0.06em] text-muted uppercase">Seats</h3>
                @foreach ($this->seats['branches'] as $data)
                    <div wire:key="branch-{{ $data['branch']->value }}" class="flex flex-col gap-1.5">
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-semibold">{{ $data['branch']->value }} · {{ $data['branch']->label() }}</span>
                            <span class="text-muted tabular-nums">{{ $data['filled'] }} of {{ $data['seats'] }} filled</span>
                        </div>
                        <span class="block h-2 overflow-hidden rounded-full bg-inactive-bg" aria-hidden="true"><span class="tp-bar tp-bar-x block h-full rounded-full bg-blue" style="width: {{ $data['seats'] > 0 ? $data['filled'] / $data['seats'] * 100 : 0 }}%"></span></span>
                    </div>
                @endforeach
                <a href="{{ route('org-structure') }}" wire:navigate class="inline-flex min-h-11 items-center self-start text-sm font-semibold text-blue hover:text-navy">
                    {{ $this->seats['unassigned'] }} active {{ Str::plural('member', $this->seats['unassigned']) }} without a seat
                </a>
            </div>

            <div class="flex flex-col gap-3">
                <h3 class="text-xs font-bold tracking-[0.06em] text-muted uppercase">By chapter</h3>
                @foreach ($this->chapters as $chapter)
                    <div wire:key="chapter-{{ $chapter->id }}" class="flex flex-col gap-1.5">
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-semibold">{{ $chapter->name }}</span>
                            <span class="text-muted tabular-nums">{{ $chapter->members_count }}</span>
                        </div>
                        <span class="block h-2 overflow-hidden rounded-full bg-inactive-bg" aria-hidden="true"><span class="tp-bar tp-bar-x block h-full rounded-full bg-blue" style="width: {{ $chapter->members_count / $maxChapter * 100 }}%"></span></span>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>
</div>
