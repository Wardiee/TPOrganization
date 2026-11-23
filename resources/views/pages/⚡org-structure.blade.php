<?php

use App\Enums\Branch;
use App\Enums\DatePrecision;
use App\Enums\HistoryKind;
use App\Enums\MemberStatus;
use App\Models\HistoryEvent;
use App\Models\Member;
use App\Models\OrgChart;
use App\Models\OrgChartSeat;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Services\NameMismatches;
use App\Services\PositionAssignments;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Org structure')] class extends Component
{
    /** "today" (live assignments) or "chart" (the latest published snapshot, read-only). */
    #[Url(except: 'today')]
    public string $view = 'today';

    /** Branch shown on phones. */
    public string $branch = 'TROP';

    public function mount(): void
    {
        $this->authorize('viewAny', Position::class);
    }

    /**
     * Add the next numbered seat to a branch, then open the pop-up to fill it.
     */
    public function addSeat(string $branch, PositionAssignments $assignments): void
    {
        $this->authorize('create', Position::class);

        $position = $assignments->addSeat(Branch::from($branch));

        $this->refreshStructure();
        $this->dispatch('open-assign-position', positionId: $position->id);
    }

    public function nextSeatCode(Branch $branch): string
    {
        return app(PositionAssignments::class)->nextSeatCode($branch);
    }

    #[On('position-assigned')]
    public function refreshStructure(): void
    {
        unset($this->positions, $this->mismatches, $this->unassigned, $this->history);
    }

    #[Computed]
    public function isToday(): bool
    {
        return $this->view !== 'chart';
    }

    #[Computed]
    public function chart(): ?OrgChart
    {
        return OrgChart::query()->latest('published_on')->latest('id')->with('seats.member.chapter')->first();
    }

    /**
     * @return Collection<int, Position>
     */
    #[Computed]
    public function positions(): Collection
    {
        return Position::query()->ordered()->with('currentAssignment.member.chapter')->get();
    }

    /**
     * @return array<int, array{member_id: int, member_name: string, position_code: string, name_as_listed: string}>
     */
    #[Computed]
    public function mismatches(): array
    {
        return app(NameMismatches::class)->find();
    }

    /**
     * One seat as shown in the current view.
     *
     * @return array{position: Position, name: string|null, member: Member|null, meta: string, flag: string|null}
     */
    public function seat(Position $position): array
    {
        if (! $this->isToday) {
            /** @var OrgChartSeat|null $seat */
            $seat = $this->chart?->seats->firstWhere('position_id', $position->id);

            return ['position' => $position, 'name' => $seat?->name_as_listed, 'member' => $seat?->member, 'meta' => $seat?->member?->chapter?->name ?? '', 'flag' => null];
        }

        /** @var PositionAssignment|null $assignment */
        $assignment = $position->currentAssignment;
        $member = $assignment?->member;

        return match (true) {
            $assignment === null => ['position' => $position, 'name' => null, 'member' => null, 'meta' => 'Empty seat', 'flag' => 'empty'],
            $member !== null && $member->status === MemberStatus::Inactive => [
                'position' => $position, 'name' => $member->full_name, 'member' => $member, 'flag' => 'inactive',
                'meta' => 'Inactive'.($member->status_changed_on ? ' since '.$member->status_changed_on->format('M j, Y') : ''),
            ],
            $member !== null && ($this->mismatches[$member->id]['position_code'] ?? null) === $position->code => [
                'position' => $position, 'name' => $member->full_name, 'member' => $member, 'flag' => 'mismatch',
                'meta' => 'Name differs from org chart',
            ],
            default => [
                'position' => $position, 'name' => $member?->full_name ?? $assignment->name_as_listed, 'member' => $member, 'flag' => null,
                'meta' => $member?->chapter?->name ?? '',
            ],
        };
    }

    /**
     * Branch summary: lead (seat 1), the other seats, and how many need attention.
     *
     * @return array{branch: Branch, title: string, meaning: string, lead: array, leadRole: string, seats: list<array>, attention: int}
     */
    public function branchData(Branch $branch): array
    {
        $seats = $this->positions->where('branch', $branch)->values()->map(fn (Position $position) => $this->seat($position));
        $lead = $seats->first();
        $rest = $seats->slice(1)->values();

        return [
            'branch' => $branch,
            'title' => $branch === Branch::Trop ? 'TROP · MAIN' : 'TROPS · EXT',
            'meaning' => $branch === Branch::Trop ? 'Long-term members' : 'Newer members',
            'lead' => $lead,
            'leadRole' => $this->rolesHeldBy($lead),
            'seats' => $rest->all(),
            'attention' => $seats->whereNotNull('flag')->count(),
        ];
    }

    /**
     * "CO-FOUNDER · TROP-1": every seat the lead holds in this view.
     *
     * @param  array{position: Position, member: Member|null}|null  $seat
     */
    private function rolesHeldBy(?array $seat): string
    {
        if ($seat === null) {
            return '';
        }

        if ($seat['member'] === null) {
            return $seat['position']->code;
        }

        return $this->positions
            ->filter(fn (Position $position) => $this->seat($position)['member']?->is($seat['member']))
            ->pluck('code')
            ->implode(' · ');
    }

    /**
     * @return array{position: Position, name: string|null, member: Member|null, meta: string, flag: string|null}|null
     */
    #[Computed]
    public function founder(): ?array
    {
        $position = $this->positions->firstWhere('code', 'FOUNDER');

        return $position === null ? null : $this->seat($position);
    }

    /**
     * Active members without a seat, in the order they were added.
     *
     * @return Collection<int, Member>
     */
    #[Computed]
    public function unassigned(): Collection
    {
        return Member::active()->whereDoesntHave('currentAssignments')->with('chapter')->orderBy('id')->get();
    }

    /**
     * Structure and membership changes, newest first.
     *
     * @return Collection<int, HistoryEvent>
     */
    #[Computed]
    public function history(): Collection
    {
        return HistoryEvent::query()->whereIn('kind', [HistoryKind::Org, HistoryKind::Members])->latestFirst()->get();
    }

    public function historyDate(HistoryEvent $event): string
    {
        return $event->date_precision === DatePrecision::Year ? $event->happened_on->format('Y') : $event->happened_on->format('M j, Y');
    }

    #[Computed]
    public function inactiveHeld(): int
    {
        return $this->positions->filter(fn (Position $position) => $position->currentAssignment?->member?->status === MemberStatus::Inactive)->count();
    }

    public function initials(?string $name): string
    {
        return $name === null ? '—' : (new Member(['full_name' => $name]))->initials;
    }
};
?>

@php
    $branches = [$this->branchData(App\Enums\Branch::Trop), $this->branchData(App\Enums\Branch::Trops)];
    $phoneBranch = collect($branches)->first(fn ($data) => $data['branch']->value === $branch) ?? $branches[0];
    $subtitle = $this->isToday
        ? 'As of today'.($this->inactiveHeld > 0 ? ' · '.$this->inactiveHeld.' '.Str::plural('seat', $this->inactiveHeld).' held by inactive members' : '')
        : 'As published on '.($this->chart?->published_on->format('M j, Y') ?? '—').' (view only)';
    $founderName = $this->founder['name'] ?? 'Not set';
    $canEdit = $this->isToday && auth()->user()->can('update', $this->positions->first() ?? new App\Models\Position);
    $flagStyles = [
        'inactive' => ['card' => 'border-[#F0C2A4] bg-[#FFF4EC]', 'row' => 'bg-[#FFF4EC]', 'meta' => 'font-semibold text-alert-fg'],
        'mismatch' => ['card' => 'border-[#E9C46A] bg-[#FAF9F4]', 'row' => 'bg-[#FFFBEA]', 'meta' => 'font-semibold text-[#6B4A00]'],
        'empty' => ['card' => 'border-dashed border-line-input bg-white', 'row' => 'bg-white', 'meta' => 'font-semibold text-muted'],
        null => ['card' => 'border-line bg-[#FAF9F4]', 'row' => 'bg-white', 'meta' => 'text-muted'],
    ];
@endphp

{{-- Designs: docs/design/web/OrgChart.dc.html (lg+), docs/design/mobile/OrgChart.dc.html (phones) --}}
<div class="contents">
    <x-page-header title="Org structure" :subtitle="$subtitle" back-to-more>
        <div role="group" aria-label="Show structure as of" class="hidden gap-1 rounded-xl bg-[#ECEAE0] p-1 lg:flex">
            @foreach (['today' => 'Today', 'chart' => $this->chart?->title ?? 'Org chart'] as $value => $label)
                <button type="button" wire:click="$set('view', '{{ $value }}')" aria-pressed="{{ $view === $value ? 'true' : 'false' }}" @class([
                    'min-h-10 rounded-[9px] px-3.5 text-sm font-semibold text-ink focus-visible:outline-2 focus-visible:outline-blue',
                    'bg-white shadow-[0_1px_3px_rgba(22,24,29,0.12)]' => $view === $value,
                ])>{{ $label }}</button>
            @endforeach
        </div>
    </x-page-header>

    {{-- Phone: view toggle --}}
    <div role="group" aria-label="Show structure as of" class="grid grid-cols-2 gap-1 rounded-xl bg-[#ECEAE0] p-1 lg:hidden">
        @foreach (['today' => 'Today', 'chart' => $this->chart?->title ?? 'Org chart'] as $value => $label)
            <button type="button" wire:click="$set('view', '{{ $value }}')" aria-pressed="{{ $view === $value ? 'true' : 'false' }}" @class([
                'min-h-10 rounded-[9px] text-sm font-semibold text-ink focus-visible:outline-2 focus-visible:outline-blue',
                'bg-white shadow-[0_1px_3px_rgba(22,24,29,0.12)]' => $view === $value,
            ])>{{ $label }}</button>
        @endforeach
    </div>

    {{-- ───────────── Phone ───────────── --}}
    <section aria-label="Founder" class="flex items-center gap-3 rounded-2xl bg-navy p-3.5 text-white lg:hidden">
        <x-avatar-initials :initials="$this->initials($this->founder['name'] ?? null)" size="touch" class="size-12! font-extrabold" />
        <span class="flex flex-col gap-0.5">
            <span class="text-[11px] font-bold tracking-[0.08em] text-yellow">FOUNDER · CHAIRMAN</span>
            <span class="text-base font-bold">{{ $founderName }}</span>
        </span>
    </section>

    <div role="group" aria-label="Branch" class="flex gap-2 lg:hidden">
        @foreach ($branches as $data)
            @php($on = $phoneBranch['branch'] === $data['branch'])
            <button type="button" wire:click="$set('branch', '{{ $data['branch']->value }}')" aria-pressed="{{ $on ? 'true' : 'false' }}" @class([
                'min-h-11 flex-1 rounded-full border text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue',
                'border-navy bg-navy text-white' => $on,
                'border-line-input bg-white text-ink' => ! $on,
            ])>{{ $data['branch']->value }} · {{ $data['branch']->label() }}</button>
        @endforeach
    </div>

    <section aria-label="{{ $phoneBranch['title'] }}" class="overflow-hidden rounded-2xl border border-line bg-white lg:hidden">
        <div class="flex items-center gap-3 border-b border-[#F0E2A8] bg-[#FFFBEA] px-4 py-3.5">
            <x-avatar-initials :initials="$this->initials($phoneBranch['lead']['name'] ?? null)" tone="active-solid" class="size-[42px]! bg-navy!" />
            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                <span class="text-[11px] font-bold tracking-[0.06em] text-floating-fg">{{ $phoneBranch['leadRole'] }}</span>
                <span class="text-[15px] font-bold">{{ $phoneBranch['lead']['name'] ?? 'Empty seat' }}</span>
                <span class="text-xs text-muted">{{ $phoneBranch['meaning'] }}</span>
            </span>
            <span class="text-xs font-semibold text-muted">
                {{ $this->isToday ? ($phoneBranch['attention'] > 0 ? $phoneBranch['attention'].' need attention' : 'All good') : (count($phoneBranch['seats']) + 1).' positions' }}
            </span>
        </div>
        <ul>
            @foreach ([$phoneBranch['lead'], ...$phoneBranch['seats']] as $seat)
                @continue($seat === null)
                <li wire:key="phone-seat-{{ $view }}-{{ $seat['position']->id }}" class="flex min-h-15 items-center gap-2.5 border-b border-line-soft px-4 py-2 last:border-b-0 {{ $flagStyles[$seat['flag']]['row'] }}">
                    <span class="w-[72px] shrink-0 text-xs font-bold text-blue">{{ $seat['position']->code }}</span>
                    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span class="truncate text-sm font-semibold">{{ $seat['name'] ?? 'Empty seat' }}</span>
                        @if ($seat['meta'] !== '' && $seat['name'] !== null)
                            <span class="text-xs {{ $flagStyles[$seat['flag']]['meta'] }}">{{ $seat['meta'] }}</span>
                        @endif
                    </span>
                    @if ($canEdit)
                        @if ($seat['flag'] === 'mismatch')
                            <a href="{{ route('members.show', $seat['member']) }}" wire:navigate class="inline-flex min-h-11 shrink-0 items-center rounded-lg border border-line-input bg-white px-3 text-xs font-semibold text-ink focus-visible:outline-2 focus-visible:outline-blue">Review</a>
                        @else
                            <button type="button" wire:click="$dispatch('open-assign-position', { positionId: {{ $seat['position']->id }} })" @class([
                                'inline-flex min-h-11 shrink-0 items-center rounded-lg px-3 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-blue',
                                'border border-line-input bg-white text-ink' => $seat['flag'] !== null,
                                'text-blue' => $seat['flag'] === null,
                            ])>{{ match ($seat['flag']) { 'inactive' => 'Reassign', 'empty' => 'Assign', default => 'Change' } }}<span class="sr-only"> {{ $seat['position']->code }}</span></button>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($this->isToday)
            @can('create', App\Models\Position::class)
                <button type="button" wire:click="addSeat('{{ $phoneBranch['branch']->value }}')" wire:confirm="Add a new seat, {{ $this->nextSeatCode($phoneBranch['branch']) }}, to {{ $phoneBranch['title'] }}?" class="flex min-h-12 w-full items-center justify-center gap-2 border-t border-line-soft text-sm font-semibold text-blue focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue">
                    <x-icon name="plus" :size="16" :stroke-width="2.4" />Add a seat ({{ $this->nextSeatCode($phoneBranch['branch']) }})
                </button>
            @endcan
        @endif
    </section>

    {{-- ───────────── Desktop chart ───────────── --}}
    <section aria-label="Organization chart" class="hidden flex-col items-stretch rounded-[20px] border border-line bg-white p-7 lg:flex">
        <div class="flex justify-center">
            <div class="flex items-center gap-3.5 rounded-[14px] bg-navy px-5 py-3.5 text-white">
                <x-avatar-initials :initials="$this->initials($this->founder['name'] ?? null)" class="size-13! font-display text-lg font-extrabold" />
                <span class="flex flex-col gap-0.5">
                    <span class="text-base font-bold">{{ $founderName }}</span>
                    <span class="text-xs font-bold tracking-[0.08em] text-yellow">CHAIRMAN / FOUNDER</span>
                </span>
            </div>
        </div>
        <div class="flex justify-center" aria-hidden="true"><span class="h-6 w-0.5 bg-navy"></span></div>
        <div class="mx-[25%] border-t-2 border-navy" aria-hidden="true"></div>

        <div class="flex flex-wrap gap-7">
            @foreach ($branches as $data)
                <div wire:key="branch-{{ $view }}-{{ $data['branch']->value }}" class="flex min-w-0 flex-[1_1_420px] flex-col items-stretch">
                    <div class="flex justify-center" aria-hidden="true"><span class="h-[22px] w-0.5 bg-navy"></span></div>
                    <div class="flex justify-center">
                        @php($lead = $data['lead'])
                        @if ($canEdit && $lead)
                            <button type="button" wire:click="$dispatch('open-assign-position', { positionId: {{ $lead['position']->id }} })" class="flex items-center gap-3 rounded-[14px] border-2 border-yellow bg-[#FFF6D1] px-4 py-3 text-left hover:bg-[#FFF0B8] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                        @else
                            <div class="flex items-center gap-3 rounded-[14px] border-2 border-yellow bg-[#FFF6D1] px-4 py-3">
                        @endif
                                <x-avatar-initials :initials="$this->initials($lead['name'] ?? null)" size="touch" class="bg-navy! text-white!" />
                                <span class="flex flex-col gap-0.5">
                                    <span class="text-[15px] font-bold">{{ $lead['name'] ?? 'Empty seat' }}</span>
                                    <span class="text-xs font-bold tracking-[0.06em] text-[#6B4A00]">{{ $data['leadRole'] }}</span>
                                </span>
                        @if ($canEdit && $lead)
                            </button>
                        @else
                            </div>
                        @endif
                    </div>
                    <div class="flex justify-center" aria-hidden="true"><span class="h-[18px] w-0.5 bg-[#CFCCC0]"></span></div>
                    <div class="flex items-baseline justify-between gap-3 border-t border-dashed border-[#CFCCC0] px-0.5 pt-3 pb-2.5">
                        <h2 class="text-[13px] font-bold tracking-[0.06em]">{{ $data['title'] }} <span class="font-semibold tracking-normal text-muted">· {{ $data['meaning'] }}</span></h2>
                        <span class="text-xs text-muted">
                            {{ $this->isToday ? ($data['attention'] > 0 ? $data['attention'].' '.Str::plural('seat', $data['attention']).' need attention' : 'All seats in order') : (count($data['seats']) + 1).' positions' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2.5">
                        @foreach ($data['seats'] as $seat)
                            @php($styles = $flagStyles[$seat['flag']])
                            @if ($canEdit)
                                <button type="button" wire:key="seat-{{ $view }}-{{ $seat['position']->id }}" wire:click="$dispatch('open-assign-position', { positionId: {{ $seat['position']->id }} })" class="flex min-w-0 flex-col gap-1.5 rounded-[10px] border px-3 py-2.5 text-left hover:border-line-input focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue {{ $styles['card'] }}" aria-label="{{ $seat['position']->code }}: {{ $seat['name'] ?? 'empty' }}{{ $seat['flag'] ? ', '.$seat['meta'] : '' }}. Change who holds this seat.">
                            @else
                                <div wire:key="seat-{{ $view }}-{{ $seat['position']->id }}" class="flex min-w-0 flex-col gap-1.5 rounded-[10px] border px-3 py-2.5 {{ $styles['card'] }}">
                            @endif
                                    <span class="flex min-w-0 items-center gap-2.5">
                                        <span class="shrink-0 rounded-md bg-navy px-1.5 py-[3px] text-[11px] font-extrabold text-white tabular-nums">{{ $seat['position']->code }}</span>
                                        <span class="truncate text-[13px] font-semibold">{{ $seat['name'] ?? 'Empty seat' }}</span>
                                    </span>
                                    <span class="text-xs {{ $styles['meta'] }}">
                                        {{ $seat['meta'] !== '' ? $seat['meta'] : ' ' }}{{ $seat['flag'] === 'inactive' ? ' · reassign' : '' }}{{ $seat['flag'] === 'mismatch' ? ' · review' : '' }}{{ $seat['flag'] === 'empty' ? ' · assign' : '' }}
                                    </span>
                            @if ($canEdit)
                                </button>
                            @else
                                </div>
                            @endif
                        @endforeach
                        @if ($this->isToday)
                            @can('create', App\Models\Position::class)
                                <button type="button" wire:click="addSeat('{{ $data['branch']->value }}')" wire:confirm="Add a new seat, {{ $this->nextSeatCode($data['branch']) }}, to {{ $data['title'] }}?" class="flex min-h-[62px] items-center justify-center gap-2 rounded-[10px] border border-dashed border-line-input text-[13px] font-semibold text-blue hover:bg-bg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                                    <x-icon name="plus" :size="16" :stroke-width="2.4" />Add a seat ({{ $this->nextSeatCode($data['branch']) }})
                                </button>
                            @endcan
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ───────────── History + unassigned ───────────── --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-start lg:gap-5">
        <x-card class="order-1 flex min-w-0 flex-col gap-2 px-4 py-3.5 lg:order-2 lg:flex-[1_1_300px] lg:gap-3 lg:p-6">
            <h2 class="text-[15px] font-bold lg:text-lg">
                <span class="lg:hidden">No position yet · {{ $this->unassigned->count() }}</span>
                <span class="hidden lg:inline">Active, no position yet</span>
            </h2>
            @forelse ($this->unassigned as $member)
                <div wire:key="unassigned-{{ $member->id }}" class="flex items-center justify-between gap-3 border-t border-line-soft py-1.5 lg:border-t-0 lg:border-b lg:py-2">
                    <span class="flex min-w-0 flex-col gap-0.5">
                        <a href="{{ route('members.show', $member) }}" wire:navigate class="text-sm font-semibold hover:text-blue">{{ $member->full_name }}</a>
                        <span class="text-xs text-muted">{{ collect([$member->nickname ? '“'.$member->nickname.'”' : null, $member->chapter?->name, $member->grade_level])->filter()->implode(' · ') }}</span>
                    </span>
                    @can('update', $this->positions->first() ?? new App\Models\Position)
                        <button type="button" wire:click="$dispatch('open-assign-position', { memberId: {{ $member->id }} })" class="min-h-11 shrink-0 rounded-lg bg-blue px-3.5 text-[13px] font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue lg:rounded-[10px] lg:border lg:border-line-input lg:bg-white lg:text-ink lg:hover:bg-bg">Assign<span class="sr-only"> a position to {{ $member->full_name }}</span></button>
                    @endcan
                </div>
            @empty
                <p class="text-sm text-muted">Every active member has a position.</p>
            @endforelse
        </x-card>

        <section aria-labelledby="history-heading" class="order-2 flex min-w-0 flex-col gap-3 lg:order-1 lg:flex-[2_1_520px] lg:gap-4 lg:rounded-2xl lg:border lg:border-line lg:bg-white lg:p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="history-heading" class="text-[15px] font-bold lg:text-lg"><span class="lg:hidden">History</span><span class="hidden lg:inline">Structure history</span></h2>
                <p class="hidden text-[13px] text-muted lg:block">Nothing is overwritten. Every change is kept here.</p>
            </div>
            <ol>
                @foreach ($this->history as $event)
                    <li wire:key="history-{{ $event->id }}" class="flex gap-3 lg:gap-4">
                        <span class="hidden w-24 shrink-0 pt-px text-[13px] font-bold tabular-nums lg:block">{{ $this->historyDate($event) }}</span>
                        <span class="flex w-3.5 shrink-0 flex-col items-center" aria-hidden="true">
                            <span class="mt-[3px] box-border size-3 shrink-0 rounded-full border-2 border-navy bg-yellow"></span>
                            @unless ($loop->last)<span class="hidden min-h-[18px] w-0.5 flex-1 bg-line lg:block"></span>@endunless
                        </span>
                        <span class="flex min-w-0 flex-col gap-0.5 pb-3 lg:gap-[3px] lg:pb-[18px]">
                            <span class="text-xs font-bold text-muted lg:hidden">{{ $this->historyDate($event) }}</span>
                            <span class="text-sm font-semibold">{{ $event->title }}</span>
                            @if ($event->detail)
                                <span class="text-xs text-muted lg:text-[13px]">{{ $event->detail }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    <livewire:assign-position />
</div>
