<?php

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\OrgChart;
use App\Models\Position;
use App\Services\PositionAssignments;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Assign / reassign pop-up, shared by Org Structure and the member profile ("Change position").
 *
 * Open it with: $dispatch('open-assign-position', { positionId: 12 }) or { memberId: 7 }.
 * Emits "position-assigned" after saving so the page can refresh.
 */
new class extends Component
{
    public bool $open = false;

    /** Opened from a seat (position fixed) or from a member (member fixed). */
    public string $mode = 'seat';

    public ?int $positionId = null;

    /** Member id as a string; '' = not chosen yet, 'empty' = leave the seat empty. */
    public string $memberId = '';

    public string $startedOn = '';

    public string $note = '';

    public bool $moveFromOtherSeats = true;

    #[On('open-assign-position')]
    public function openFor(?int $positionId = null, ?int $memberId = null): void
    {
        $this->resetValidation();
        $this->reset('note');
        $this->moveFromOtherSeats = true;
        $this->startedOn = CarbonImmutable::today()->toDateString();

        $this->mode = $positionId !== null ? 'seat' : 'member';
        $this->positionId = $positionId;
        $this->memberId = $memberId !== null ? (string) $memberId : '';

        if ($this->position !== null) {
            $this->authorize('update', $this->position);
        }

        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    #[Computed]
    public function position(): ?Position
    {
        return $this->positionId === null ? null : Position::query()
            ->with(['currentAssignment.member'])
            ->find($this->positionId);
    }

    #[Computed]
    public function member(): ?Member
    {
        return in_array($this->memberId, ['', 'empty'], true) ? null : Member::query()
            ->with('currentAssignments.position')
            ->find((int) $this->memberId);
    }

    /**
     * Seats that can be assigned (everything except the Founder's), with who holds them now.
     *
     * @return Collection<int, Position>
     */
    #[Computed]
    public function positions(): Collection
    {
        return Position::query()->where('code', '!=', 'FOUNDER')->with('currentAssignment.member')->ordered()->get();
    }

    /**
     * Active members to choose from: those without a seat first, then the rest, A to Z.
     *
     * @return Collection<int, Member>
     */
    #[Computed]
    public function candidates(): Collection
    {
        return Member::active()
            ->with('currentAssignments.position')
            ->orderBy('full_name')
            ->get()
            ->sortBy(fn (Member $member) => $member->currentAssignments->isEmpty() ? 0 : 1)
            ->values();
    }

    /**
     * The chosen member's other TROP/TROPS seats, which "move" would end.
     *
     * @return Collection<int, Position>
     */
    #[Computed]
    public function otherSeats(): Collection
    {
        return ($this->member?->currentPositions() ?? collect())
            ->filter(fn (Position $position) => $position->branch !== null && $position->id !== $this->positionId)
            ->values();
    }

    /**
     * Name printed on the latest org chart for this seat, when it differs from the holder's (for review).
     */
    #[Computed]
    public function nameOnChart(): ?string
    {
        $holder = $this->position?->currentAssignment?->member;

        if ($holder === null) {
            return null;
        }

        $listed = OrgChart::query()->latest('published_on')->latest('id')->first()
            ?->seats()->where('position_id', $this->positionId)->value('name_as_listed');

        return $listed !== null && ! App\Support\PersonName::sameLastName($holder->full_name, $listed) ? $listed : null;
    }

    public function save(PositionAssignments $assignments): void
    {
        $position = Position::query()->with('currentAssignment')->find($this->positionId);
        $currentHolderId = $position?->currentAssignment?->member_id;
        $earliest = $position?->currentAssignment?->started_on?->toDateString();

        $this->validate([
            'positionId' => ['required', 'integer', Rule::exists('positions', 'id')->whereNot('code', 'FOUNDER')],
            'memberId' => $this->memberId === 'empty' && $position?->currentAssignment !== null
                ? ['required']
                : [
                    'required',
                    Rule::exists('members', 'id')->where('status', MemberStatus::Active->value)->whereNull('deleted_at'),
                    Rule::notIn(array_filter([(string) $currentHolderId])),
                ],
            'startedOn' => array_filter(['required', 'date', 'before_or_equal:today', $earliest ? "after_or_equal:{$earliest}" : null]),
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'memberId.required' => 'Choose who takes this seat.',
            'memberId.not_in' => 'This member already holds the seat.',
            'memberId.exists' => 'Choose an active member.',
            'startedOn.after_or_equal' => 'The current holder started on '.($earliest ? CarbonImmutable::parse($earliest)->format('M j, Y') : '').'; pick that date or later.',
            'startedOn.before_or_equal' => 'The date can’t be in the future.',
        ], ['positionId' => 'seat', 'memberId' => 'member', 'startedOn' => 'date']);

        $this->authorize('update', $position);

        $assignments->assign(
            $position,
            $this->memberId === 'empty' ? null : Member::findOrFail((int) $this->memberId),
            CarbonImmutable::parse($this->startedOn),
            $this->note,
            $this->moveFromOtherSeats,
        );

        $this->open = false;
        $this->dispatch('position-assigned');
    }
};
?>

@php
    $input = 'min-h-11 w-full rounded-[10px] border border-line-input bg-white px-3 text-[15px] text-ink focus:outline-2 focus:outline-blue aria-invalid:border-absent';
    $holderLabel = function (App\Models\Position $position): string {
        $assignment = $position->currentAssignment;
        $name = $assignment?->member?->full_name ?? $assignment?->name_as_listed;

        if ($name === null) {
            return 'empty';
        }

        return $assignment?->member && $assignment->member->status !== App\Enums\MemberStatus::Active
            ? $name.' ('.Str::lower($assignment->member->status->label()).')'
            : $name;
    };
@endphp

<div>
    @if ($open)
        {{-- wire:ignore.self keeps Livewire from stripping the dialog's "open" state when the form re-renders. --}}
        <dialog
            wire:ignore.self
            x-data
            x-init="$el.showModal()"
            x-on:close="$wire.close()"
            aria-labelledby="assign-heading"
            class="m-auto w-[min(32rem,calc(100%-2rem))] rounded-2xl border border-line bg-white p-0 text-ink shadow-xl backdrop:bg-ink/50"
        >
            <form wire:submit="save" class="flex flex-col gap-4 p-5 lg:p-6" novalidate>
                <div class="flex items-start justify-between gap-3">
                    <div class="flex flex-col gap-1">
                        <h2 id="assign-heading" class="font-display text-[22px] font-extrabold">
                            @if ($mode === 'seat' && $this->position)
                                {{ $this->position->currentAssignment ? 'Reassign' : 'Assign' }} {{ $this->position->code }}
                            @else
                                {{ $this->member?->currentAssignments->isNotEmpty() ? 'Change position' : 'Assign a position' }}
                            @endif
                        </h2>
                        <p class="text-[13px] text-muted">The current holder’s time in the seat is ended with this date and kept in history.</p>
                    </div>
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue" aria-label="Close">
                        <x-icon name="close" :size="20" />
                    </button>
                </div>

                @if ($mode === 'seat' && $this->position)
                    <div class="flex flex-col gap-1 rounded-[10px] bg-bg px-3.5 py-3 text-sm">
                        <p><span class="text-muted">Held now by</span> <span class="font-semibold">{{ $holderLabel($this->position) }}</span></p>
                        @if ($this->nameOnChart)
                            <p class="text-[13px] text-floating-fg">
                                Org chart lists “{{ $this->nameOnChart }}”.
                                <a href="{{ route('members.show', $this->position->currentAssignment->member_id) }}" wire:navigate class="font-semibold text-blue underline-offset-2 hover:underline">Review member</a>
                            </p>
                        @endif
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="assign-member" class="text-sm font-semibold">New holder</label>
                        <select id="assign-member" wire:model.live="memberId" @error('memberId') aria-invalid="true" aria-describedby="assign-member-error" @enderror class="{{ $input }}">
                            <option value="">Choose a member</option>
                            @if ($this->position->currentAssignment)
                                <option value="empty">Leave the seat empty</option>
                            @endif
                            <optgroup label="Active, no position yet">
                                @foreach ($this->candidates->filter(fn ($candidate) => $candidate->currentAssignments->isEmpty()) as $candidate)
                                    <option value="{{ $candidate->id }}">{{ $candidate->full_name }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Active, already holds a seat">
                                @foreach ($this->candidates->filter(fn ($candidate) => $candidate->currentAssignments->isNotEmpty()) as $candidate)
                                    <option value="{{ $candidate->id }}">{{ $candidate->full_name }} · {{ $candidate->currentPositions()->pluck('code')->implode(', ') }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                        @error('memberId') <p id="assign-member-error" class="text-sm text-absent">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div class="flex flex-col gap-1 rounded-[10px] bg-bg px-3.5 py-3 text-sm">
                        <p class="font-semibold">{{ $this->member?->full_name }}</p>
                        <p class="text-[13px] text-muted">{{ $this->member?->currentPositions()->map->shortLabel()->implode(', ') ?: 'No position yet' }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="assign-position" class="text-sm font-semibold">Seat</label>
                        <select id="assign-position" wire:model.live="positionId" @error('positionId') aria-invalid="true" aria-describedby="assign-position-error" @enderror class="{{ $input }}">
                            <option value="">Choose a seat</option>
                            @foreach ($this->positions->groupBy(fn ($position) => $position->branch?->value ?? 'other') as $group => $positions)
                                <optgroup label="{{ match ($group) { 'TROP' => 'TROP · Main (long-term members)', 'TROPS' => 'TROPS · Ext (newer members)', default => 'Founders' } }}">
                                    @foreach ($positions as $position)
                                        <option value="{{ $position->id }}">{{ $position->code }} · {{ $holderLabel($position) }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('positionId') <p id="assign-position-error" class="text-sm text-absent">{{ $message }}</p> @enderror
                        @error('memberId') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if ($this->otherSeats->isNotEmpty())
                    <label class="flex cursor-pointer items-start gap-3 rounded-[10px] border border-line px-3.5 py-3">
                        <input type="checkbox" wire:model="moveFromOtherSeats" class="mt-0.5 size-5 accent-blue focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                        <span class="flex flex-col gap-0.5">
                            <span class="text-sm font-semibold">Move from {{ $this->otherSeats->pluck('code')->implode(', ') }}</span>
                            <span class="text-[13px] text-muted">That seat is ended on the same date. Leave unticked to hold both.</span>
                        </span>
                    </label>
                @endif

                <div class="flex flex-col gap-1.5">
                    <label for="assign-date" class="text-sm font-semibold">Starting</label>
                    <input id="assign-date" type="date" wire:model="startedOn" max="{{ now()->toDateString() }}" @error('startedOn') aria-invalid="true" aria-describedby="assign-date-error" @enderror class="{{ $input }}">
                    @error('startedOn') <p id="assign-date-error" class="text-sm text-absent">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="assign-note" class="text-sm font-semibold">Note <span class="font-normal text-muted">(optional)</span></label>
                    <textarea id="assign-note" rows="2" wire:model="note" placeholder="e.g. Promoted after a year in TROPS" class="{{ $input }} py-2"></textarea>
                    @error('note') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="min-h-11 rounded-[10px] border border-line-input bg-white px-5 text-sm font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue">Cancel</button>
                    <button type="submit" class="min-h-11 rounded-[10px] bg-blue px-6 text-sm font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                        {{ $memberId === 'empty' ? 'Empty the seat' : 'Save' }}
                    </button>
                </div>
            </form>
        </dialog>
    @endif
</div>
