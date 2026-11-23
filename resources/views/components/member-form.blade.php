@props([
    'form',
    'chapters',
    'gradeSuggestions',
    'cancelUrl',
    'submitLabel',
])

@php
    /** @var \App\Livewire\Forms\MemberForm $form */
    $input = 'min-h-11 w-full rounded-[10px] border border-line-input bg-white px-3 text-[15px] text-ink focus:outline-2 focus:outline-blue aria-invalid:border-absent';
    $label = 'text-sm font-semibold text-ink';
    $months = collect(range(1, 12))->mapWithKeys(fn (int $month) => [$month => \Carbon\CarbonImmutable::create(2000, $month, 1)->format('F')]);
    $statusChanged = $form->member !== null && $form->status !== $form->member->status->value;
@endphp

{{-- Field layout follows the profile (docs/design/web/Profile.dc.html). --}}
<form wire:submit="save" class="flex flex-col gap-4 lg:gap-5" novalidate>
    <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-start lg:gap-5">
        <x-card class="flex min-w-0 flex-col gap-4 p-4 lg:flex-[1_1_360px] lg:p-6">
            <h2 class="text-base font-bold lg:text-lg">Personal information</h2>

            <div class="flex flex-col gap-1.5">
                <label for="full_name" class="{{ $label }}">Full name</label>
                <input id="full_name" type="text" wire:model="form.full_name" autocomplete="off" required @error('form.full_name') aria-invalid="true" aria-describedby="full_name-error" @enderror class="{{ $input }}">
                @error('form.full_name') <p id="full_name-error" class="text-sm text-absent">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="nickname" class="{{ $label }}">Nickname <span class="font-normal text-muted">(optional)</span></label>
                <input id="nickname" type="text" wire:model="form.nickname" autocomplete="off" @error('form.nickname') aria-invalid="true" @enderror class="{{ $input }}">
                @error('form.nickname') <p class="text-sm text-absent">{{ $message }}</p> @enderror
            </div>

            <fieldset class="flex flex-col gap-1.5">
                <legend class="{{ $label }}">Birthday</legend>
                <p class="mb-0.5 text-[13px] text-muted">Leave the year empty if it isn’t known. Age is calculated from it.</p>
                <div class="grid grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,1.2fr)] gap-2">
                    <label class="flex flex-col gap-1">
                        <span class="text-xs text-muted">Month</span>
                        <select wire:model="form.birth_month" @error('form.birth_month') aria-invalid="true" @enderror class="{{ $input }}">
                            <option value="">—</option>
                            @foreach ($months as $number => $name)
                                <option value="{{ $number }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="text-xs text-muted">Day</span>
                        <select wire:model="form.birth_day" @error('form.birth_day') aria-invalid="true" @enderror class="{{ $input }}">
                            <option value="">—</option>
                            @foreach (range(1, 31) as $day)
                                <option value="{{ $day }}">{{ $day }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="text-xs text-muted">Year <span class="sr-only">(optional)</span></span>
                        <input type="number" inputmode="numeric" wire:model="form.birth_year" min="1900" max="{{ now()->year }}" placeholder="yyyy" @error('form.birth_year') aria-invalid="true" @enderror class="{{ $input }}">
                    </label>
                </div>
                @foreach (['form.birth_month', 'form.birth_day', 'form.birth_year'] as $field)
                    @error($field) <p class="text-sm text-absent">{{ $message }}</p> @enderror
                @endforeach
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="grade_level" class="{{ $label }}">Grade level <span class="font-normal text-muted">(optional)</span></label>
                    <input id="grade_level" type="text" list="grade-levels" wire:model="form.grade_level" autocomplete="off" class="{{ $input }}">
                    <datalist id="grade-levels">
                        @foreach ($gradeSuggestions as $grade)
                            <option value="{{ $grade }}"></option>
                        @endforeach
                    </datalist>
                    @error('form.grade_level') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="chapter_id" class="{{ $label }}">Chapter</label>
                    <select id="chapter_id" wire:model="form.chapter_id" @error('form.chapter_id') aria-invalid="true" @enderror class="{{ $input }}">
                        <option value="">Not recorded</option>
                        @foreach ($chapters as $chapter)
                            <option value="{{ $chapter->id }}">{{ $chapter->name }}</option>
                        @endforeach
                    </select>
                    @error('form.chapter_id') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-card>

        <x-card class="flex min-w-0 flex-col gap-4 p-4 lg:flex-[1_1_360px] lg:p-6">
            <h2 class="text-base font-bold lg:text-lg">Membership</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="member_no" class="{{ $label }}">Member no. <span class="font-normal text-muted">(optional)</span></label>
                    <input id="member_no" type="number" inputmode="numeric" min="1" wire:model="form.member_no" @error('form.member_no') aria-invalid="true" @enderror class="{{ $input }}">
                    @error('form.member_no') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="joined_on" class="{{ $label }}">Date joined <span class="font-normal text-muted">(optional)</span></label>
                    <input id="joined_on" type="date" wire:model="form.joined_on" max="{{ now()->toDateString() }}" @error('form.joined_on') aria-invalid="true" @enderror class="{{ $input }}">
                    @error('form.joined_on') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                </div>
            </div>

            <fieldset class="flex flex-col gap-1.5">
                <legend class="{{ $label }}">Status</legend>
                <div class="grid grid-cols-3 gap-1 rounded-xl bg-[#ECEAE0] p-1">
                    @foreach (App\Enums\MemberStatus::cases() as $status)
                        <label class="relative flex min-h-11 cursor-pointer items-center justify-center rounded-[9px] text-sm font-semibold text-ink has-checked:bg-white has-checked:shadow-[0_1px_3px_rgba(22,24,29,0.12)] has-focus-visible:outline-2 has-focus-visible:outline-blue">
                            <input type="radio" wire:model.live="form.status" value="{{ $status->value }}" class="sr-only">
                            {{ $status->label() }}
                        </label>
                    @endforeach
                </div>
                @error('form.status') <p class="text-sm text-absent">{{ $message }}</p> @enderror
            </fieldset>

            @if ($form->member === null && $form->status === App\Enums\MemberStatus::Active->value)
                @php
                    $placementBranch = App\Enums\Branch::tryFrom($form->placement);
                    $emptySeats = $placementBranch ? App\Livewire\Forms\MemberForm::emptySeats($placementBranch) : collect();
                    $nextSeat = $placementBranch ? app(App\Services\PositionAssignments::class)->nextSeatCode($placementBranch) : null;
                @endphp
                <fieldset class="flex flex-col gap-1.5">
                    <legend class="{{ $label }}">Place in the org structure</legend>
                    <div class="grid grid-cols-3 gap-1 rounded-xl bg-[#ECEAE0] p-1">
                        @foreach (['' => ['No position yet', ''], 'TROP' => ['TROP · Main', 'Long-term'], 'TROPS' => ['TROPS · Ext', 'Newer']] as $value => [$title, $hint])
                            <label class="relative flex min-h-13 cursor-pointer flex-col items-center justify-center rounded-[9px] px-1 text-center text-[13px] leading-tight font-semibold text-ink has-checked:bg-white has-checked:shadow-[0_1px_3px_rgba(22,24,29,0.12)] has-focus-visible:outline-2 has-focus-visible:outline-blue">
                                <input type="radio" wire:model.live="form.placement" value="{{ $value }}" class="sr-only">
                                {{ $title }}
                                @if ($hint)<span class="text-[11px] font-medium text-muted">{{ $hint }} members</span>@endif
                            </label>
                        @endforeach
                    </div>
                    @error('form.placement') <p class="text-sm text-absent">{{ $message }}</p> @enderror

                    @if ($placementBranch)
                        <label for="seat" class="mt-1.5 text-[13px] font-semibold">Seat</label>
                        <select id="seat" wire:model="form.seat" @error('form.seat') aria-invalid="true" @enderror class="{{ $input }}">
                            @foreach ($emptySeats as $emptySeat)
                                <option value="{{ $emptySeat->id }}">{{ $emptySeat->code }} (empty)</option>
                            @endforeach
                            <option value="new">New seat: {{ $nextSeat }}</option>
                        </select>
                        @error('form.seat') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                        <p class="text-[13px] text-muted">The seat starts on the date joined, or today if that’s empty. You can change it later from Org Structure.</p>
                    @endif
                </fieldset>
            @endif

            @if ($statusChanged)
                <div class="flex flex-col gap-1.5 rounded-[10px] bg-[#FFF6D9] p-3.5">
                    <label for="status_note" class="text-sm font-semibold text-[#5A3A00]">
                        Changing from {{ $form->member->status->label() }} to {{ App\Enums\MemberStatus::from($form->status)->label() }}
                    </label>
                    <p class="text-[13px] text-[#5A3A00]">This is kept in the member’s history with today’s date. Add a reason if you like.</p>
                    <textarea id="status_note" rows="2" wire:model="form.status_note" placeholder="Reason (optional)" class="{{ $input }} py-2"></textarea>
                    @error('form.status_note') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="flex flex-col gap-1.5">
                <label for="remarks" class="{{ $label }}">Founder’s remark <span class="font-normal text-muted">(optional)</span></label>
                <textarea id="remarks" rows="2" wire:model="form.remarks" placeholder="e.g. Needs more participation" class="{{ $input }} py-2"></textarea>
                @error('form.remarks') <p class="text-sm text-absent">{{ $message }}</p> @enderror
            </div>
        </x-card>
    </div>

    <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
        <a href="{{ $cancelUrl }}" wire:navigate class="inline-flex min-h-12 items-center justify-center rounded-[10px] border border-line-input bg-white px-5 text-[15px] font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue lg:min-h-11 lg:text-sm">Cancel</a>
        <button type="submit" wire:loading.attr="disabled" class="inline-flex min-h-12 items-center justify-center rounded-[10px] bg-blue px-6 text-[15px] font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue disabled:opacity-70 lg:min-h-11 lg:text-sm">
            <span wire:loading.remove wire:target="save">{{ $submitLabel }}</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </div>
</form>
