<?php

use App\Models\Member;
use App\Services\CalendarDates;
use App\Services\UpcomingBirthdays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Birthdays & dates')] class extends Component
{
    /** Month shown, as "YYYY-MM". */
    #[Url(as: 'month')]
    public string $month = '';

    /** Day tapped on phones (0 = none). */
    public int $selectedDay = 0;

    public bool $includeNotActive = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Member::class);

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month)) {
            $this->month = CarbonImmutable::today()->format('Y-m');
        }

        $this->selectedDay = $this->isCurrentMonth() ? CarbonImmutable::today()->day : 0;
    }

    #[Computed]
    public function firstOfMonth(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->month);
    }

    public function previousMonth(): void
    {
        $this->goTo($this->firstOfMonth->subMonthNoOverflow());
    }

    public function nextMonth(): void
    {
        $this->goTo($this->firstOfMonth->addMonthNoOverflow());
    }

    public function goToToday(): void
    {
        $this->goTo(CarbonImmutable::today());
        $this->selectedDay = CarbonImmutable::today()->day;
    }

    public function selectDay(int $day): void
    {
        $this->selectedDay = max(0, min($day, $this->firstOfMonth->daysInMonth));
    }

    private function goTo(CarbonImmutable $date): void
    {
        $this->month = $date->format('Y-m');
        $this->selectedDay = 0;
        unset($this->firstOfMonth, $this->days);
    }

    private function isCurrentMonth(): bool
    {
        return $this->month === CarbonImmutable::today()->format('Y-m');
    }

    /**
     * @return array<int, list<array{kind: string, label: string, title: string, detail: string, url: string|null}>>
     */
    #[Computed]
    public function days(): array
    {
        return app(CalendarDates::class)->forMonth($this->firstOfMonth->year, $this->firstOfMonth->month, $this->includeNotActive);
    }

    /**
     * Active members' birthdays in the next 60 days, soonest first.
     */
    #[Computed]
    public function upcoming(): Collection
    {
        return app(UpcomingBirthdays::class)->from(CarbonImmutable::today(), withinDays: 60);
    }

    /**
     * Members with no birthday recorded, grouped by status.
     *
     * @return Collection<string, Collection<int, Member>>
     */
    #[Computed]
    public function withoutBirthday(): Collection
    {
        return Member::query()
            ->where(fn ($query) => $query->whereNull('birth_month')->orWhereNull('birth_day'))
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Member $member) => $member->status->value);
    }

    public function inDays(int $days): string
    {
        return match ($days) {
            0 => 'today',
            1 => 'tomorrow',
            default => "in {$days} days",
        };
    }

    /**
     * @param  array{member: Member, turns: int|null}  $birthday
     */
    public function birthdayDetail(array $birthday): string
    {
        return collect([
            $birthday['member']->nickname,
            $birthday['turns'] === null ? 'birth year missing' : 'turns '.$birthday['turns'],
        ])->filter()->implode(' · ');
    }
};
?>

@php
    $first = $this->firstOfMonth;
    $leadingBlanks = $first->dayOfWeek;
    $today = now();
    $isTodayCell = fn (int $day) => $first->year === $today->year && $first->month === $today->month && $day === $today->day;
    $kindColors = [
        App\Services\CalendarDates::KIND_BIRTHDAY => ['chip' => 'bg-[#FFF1B8] text-[#4A3600]', 'dot' => 'bg-[#E0A800]', 'bar' => 'bg-[#E0A800]'],
        App\Services\CalendarDates::KIND_ACTIVITY => ['chip' => 'bg-blue text-white', 'dot' => 'bg-blue', 'bar' => 'bg-blue'],
        App\Services\CalendarDates::KIND_ORG => ['chip' => 'bg-[#0B7A80] text-white', 'dot' => 'bg-[#0B7A80]', 'bar' => 'bg-[#0B7A80]'],
    ];
    $selectedItems = $selectedDay > 0 ? ($this->days[$selectedDay] ?? []) : [];
    $noBirthday = $this->withoutBirthday;
    $noBirthdayCount = $noBirthday->flatten()->count();
@endphp

{{-- Designs: docs/design/web/Birthdays.dc.html (lg+), docs/design/mobile/Calendar.dc.html (phones) --}}
<div class="contents">
    {{-- Headers --}}
    <header class="flex items-center justify-between gap-3 pt-4 lg:hidden">
        <h1 class="font-display text-[28px] font-extrabold tracking-[-0.01em]">Birthdays</h1>
        <button type="button" wire:click="goToToday" class="min-h-11 rounded-full border border-line-input bg-white px-4 text-[13px] font-semibold text-ink focus-visible:outline-2 focus-visible:outline-blue">Today</button>
    </header>
    <header class="hidden flex-col gap-1.5 lg:flex">
        <h1 class="font-display text-[32px] font-extrabold tracking-[-0.01em]">Birthdays &amp; Important Dates</h1>
        <p class="text-sm text-muted">Member birthdays, activities and organization milestones in one calendar</p>
    </header>

    <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-start lg:gap-5">
        {{-- ───────────── Calendar ───────────── --}}
        <section aria-labelledby="calendar-heading" class="flex min-w-0 flex-col gap-2 rounded-[18px] border border-line bg-white p-3 lg:flex-[999_1_600px] lg:gap-3.5 lg:rounded-2xl lg:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex w-full items-center justify-between gap-2 lg:w-auto lg:justify-start">
                    <button type="button" wire:click="previousMonth" aria-label="Previous month" class="flex size-11 items-center justify-center rounded-[10px] text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue lg:border lg:border-line-input lg:bg-white">
                        <x-icon name="chevron-left" :size="18" />
                    </button>
                    <h2 id="calendar-heading" class="text-center font-display text-lg font-extrabold lg:min-w-[190px] lg:text-[22px]" aria-live="polite">{{ $first->format('F Y') }}</h2>
                    <button type="button" wire:click="nextMonth" aria-label="Next month" class="flex size-11 items-center justify-center rounded-[10px] text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue lg:border lg:border-line-input lg:bg-white">
                        <x-icon name="chevron-right" :size="18" />
                    </button>
                    <button type="button" wire:click="goToToday" class="hidden min-h-11 rounded-[10px] border border-line-input bg-white px-3.5 text-[13px] font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue lg:block">Today</button>
                </div>
                <div class="hidden flex-wrap items-center gap-3.5 text-[13px] lg:flex">
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-yellow"></span>Birthday</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-blue"></span>Activity</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-[#0B7A80]"></span>Org milestone</span>
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-1.5">
                        <input type="checkbox" wire:model.live="includeNotActive" class="size-[18px] accent-blue">
                        Include floating &amp; inactive
                    </label>
                </div>
            </div>

            {{-- Desktop month grid with chips --}}
            <div class="hidden overflow-x-auto lg:block">
                <div class="flex min-w-[640px] flex-col gap-1.5" role="grid" aria-labelledby="calendar-heading">
                    <div class="grid grid-cols-7 gap-1.5" role="row">
                        @foreach (['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'] as $dayName)
                            <span role="columnheader" class="px-1.5 text-xs font-bold tracking-[0.06em] text-muted">{{ $dayName }}</span>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-7 gap-1.5">
                        @for ($blank = 0; $blank < $leadingBlanks; $blank++)
                            <div class="min-h-24" aria-hidden="true"></div>
                        @endfor
                        @for ($day = 1; $day <= $first->daysInMonth; $day++)
                            @php($items = $this->days[$day] ?? [])
                            <div wire:key="cell-{{ $month }}-{{ $day }}" role="gridcell" @if ($isTodayCell($day)) aria-current="date" @endif @class([
                                'flex min-h-24 min-w-0 flex-col gap-1 rounded-[10px] p-1.5',
                                'bg-[#FFFBEA]' => $items !== [],
                                'bg-[#FAF9F4]' => $items === [],
                                'border-2 border-blue' => $isTodayCell($day),
                                'border border-[#EEECE3]' => ! $isTodayCell($day),
                            ])>
                                <span @class(['text-[13px]', 'font-extrabold text-blue' => $isTodayCell($day), 'font-semibold' => ! $isTodayCell($day)])>{{ $day }}</span>
                                @foreach ($items as $item)
                                    <a href="{{ $item['url'] }}" wire:navigate title="{{ $item['title'] }} — {{ $item['detail'] }}" class="block truncate rounded-md px-1.5 py-[3px] text-[11px] font-semibold focus-visible:outline-2 focus-visible:outline-blue {{ $kindColors[$item['kind']]['chip'] }}">{{ $item['label'] }}</a>
                                @endforeach
                            </div>
                        @endfor
                    </div>
                </div>
            </div>

            {{-- Phone month grid with dots --}}
            <div class="lg:hidden">
                <div class="grid grid-cols-7 gap-0.5" aria-hidden="true">
                    @foreach (['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $dayName)
                        <span class="py-1 text-center text-[11px] font-bold text-muted">{{ $dayName }}</span>
                    @endforeach
                </div>
                <div class="grid grid-cols-7 gap-0.5">
                    @for ($blank = 0; $blank < $leadingBlanks; $blank++)
                        <span aria-hidden="true"></span>
                    @endfor
                    @for ($day = 1; $day <= $first->daysInMonth; $day++)
                        @php($items = $this->days[$day] ?? [])
                        @php($on = $day === $selectedDay)
                        <button
                            type="button"
                            wire:key="phone-cell-{{ $month }}-{{ $day }}"
                            wire:click="selectDay({{ $day }})"
                            aria-pressed="{{ $on ? 'true' : 'false' }}"
                            aria-label="{{ $first->format('F') }} {{ $day }}{{ count($items) ? ', '.count($items).' '.Str::plural('event', count($items)) : '' }}"
                            @if ($isTodayCell($day)) aria-current="date" @endif
                            @class([
                                'flex h-[46px] flex-col items-center justify-center gap-[3px] rounded-[10px] text-sm focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-blue',
                                'bg-navy font-bold text-white' => $on,
                                'border-2 border-blue font-bold' => $isTodayCell($day) && ! $on,
                                'font-medium' => ! $on && ! $isTodayCell($day),
                            ])
                        >
                            <span>{{ $day }}</span>
                            <span class="flex h-1.5 gap-[3px]">
                                @foreach (collect($items)->pluck('kind')->unique() as $kind)
                                    <span class="size-1.5 rounded-full {{ $on ? 'bg-yellow' : $kindColors[$kind]['dot'] }}"></span>
                                @endforeach
                            </span>
                        </button>
                    @endfor
                </div>
                <div class="flex gap-3.5 px-1 pt-2 text-xs text-muted">
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-[#E0A800]"></span>Birthday</span>
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-blue"></span>Activity</span>
                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-[#0B7A80]"></span>Org update</span>
                </div>
            </div>
        </section>

        {{-- Phone: the tapped day --}}
        <section aria-labelledby="day-heading" class="flex flex-col gap-2 lg:hidden" aria-live="polite">
            <h2 id="day-heading" class="text-[15px] font-bold">{{ $selectedDay > 0 ? $first->format('F').' '.$selectedDay : 'Tap a day to see what’s on' }}</h2>
            @if ($selectedDay > 0 && $selectedItems === [])
                <p class="rounded-[14px] border border-dashed border-line-input bg-white px-4 py-3.5 text-sm text-muted">Nothing on this day.</p>
            @endif
            @foreach ($selectedItems as $item)
                <a href="{{ $item['url'] }}" wire:navigate wire:key="day-item-{{ $selectedDay }}-{{ $loop->index }}" class="flex items-center gap-3 rounded-[14px] border border-line bg-white px-3.5 py-3 focus-visible:outline-2 focus-visible:outline-blue">
                    <span class="h-10 w-2.5 shrink-0 rounded-full {{ $kindColors[$item['kind']]['bar'] }}" aria-hidden="true"></span>
                    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span class="text-[15px] font-semibold">{{ $item['title'] }}</span>
                        <span class="text-xs text-muted">{{ $item['detail'] }}</span>
                    </span>
                </a>
            @endforeach
        </section>

        {{-- ───────────── Side column ───────────── --}}
        <div class="flex min-w-0 flex-col gap-4 lg:flex-[1_1_320px] lg:gap-5">
            <section aria-labelledby="upcoming-heading" class="flex flex-col rounded-2xl border border-line bg-white px-4 py-1.5 lg:gap-3 lg:border-0 lg:bg-navy lg:p-[22px] lg:text-white">
                <h2 id="upcoming-heading" class="pt-3 pb-1 text-base font-bold lg:p-0 lg:text-lg">Next 60 days</h2>
                <ul>
                    @forelse ($this->upcoming as $birthday)
                        <li wire:key="upcoming-{{ $birthday['member']->id }}" class="border-t border-line-soft lg:border-t-0 lg:border-b lg:border-[#323C86]">
                            <a href="{{ route('members.show', $birthday['member']) }}" wire:navigate class="flex items-center gap-3 py-2.5 focus-visible:outline-2 focus-visible:outline-blue lg:py-1.5 lg:focus-visible:outline-yellow">
                                <x-date-badge :date="$birthday['date']" size="sm" class="lg:hidden" />
                                <x-date-badge :date="$birthday['date']" size="sm" tone="yellow" class="hidden size-[46px]! lg:flex" />
                                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span class="text-sm font-semibold">{{ $birthday['member']->full_name }}</span>
                                    <span class="text-xs text-muted lg:text-navy-text">{{ $this->birthdayDetail($birthday) }}</span>
                                </span>
                                <span class="text-xs font-semibold whitespace-nowrap text-muted lg:text-yellow">{{ $this->inDays($birthday['days']) }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="border-t border-line-soft py-3 text-sm text-muted lg:border-0 lg:text-navy-text">No birthdays in the next 60 days.</li>
                    @endforelse
                </ul>
            </section>

            <x-card class="hidden flex-col gap-1.5 p-[22px] lg:flex">
                <h2 class="mb-1.5 text-lg font-bold">Reminders</h2>
                <div class="flex min-h-11 items-center justify-between gap-3 text-sm">
                    <span>Birthday greeting tasks</span>
                    <span class="text-[13px] text-muted">Added to Tasks 30 days ahead</span>
                </div>
                <div class="flex min-h-11 items-center justify-between gap-3 text-sm text-muted">
                    <span>Phone notifications</span>
                    <span class="rounded-full bg-inactive-bg px-2 py-0.5 text-[11px] font-semibold text-inactive-fg">Soon</span>
                </div>
            </x-card>

            @if ($noBirthdayCount > 0)
                <x-card class="hidden flex-col gap-2.5 p-[22px] lg:flex">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 class="text-lg font-bold">No birthday on file</h2>
                        <span class="text-[13px] text-muted">{{ $noBirthdayCount }} {{ Str::plural('member', $noBirthdayCount) }}</span>
                    </div>
                    <p class="text-[13px] text-muted">
                        @if ($noBirthday->has('active'))
                            Active: {{ $noBirthday['active']->pluck('full_name')->implode(', ') }}.
                        @endif
                        @foreach (['floating', 'inactive'] as $status)
                            @if ($noBirthday->has($status))
                                Plus {{ $noBirthday[$status]->count() }} {{ $status }} {{ Str::plural('member', $noBirthday[$status]->count()) }}.
                            @endif
                        @endforeach
                    </p>
                    <a href="{{ route('members.index', ['missing' => 1]) }}" wire:navigate class="text-sm font-semibold text-blue hover:text-navy">Fill in from Members</a>
                </x-card>
            @endif
        </div>
    </div>
</div>
