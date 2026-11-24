<?php

use App\Enums\AttendanceStatus;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Document;
use App\Models\Member;
use App\Models\MemberStatusChange;
use App\Models\PositionAssignment;
use App\Services\MemberPhotos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Member profile')] class extends Component
{
    use WithFileUploads;

    public Member $member;

    /** A new profile photo, saved as soon as it arrives. */
    public ?TemporaryUploadedFile $photo = null;

    public string $photoError = '';

    public string $privateNotes = '';

    public bool $notesSaved = false;

    public function mount(): void
    {
        $this->authorize('view', $this->member);

        $this->loadProfileRelations();

        if (auth()->user()->can('viewPrivateNotes', $this->member)) {
            $this->privateNotes = (string) $this->member->private_notes;
        }
    }

    /**
     * Livewire restores the member without its loaded relations on each action, so load them again.
     */
    public function hydrate(): void
    {
        $this->loadProfileRelations();
    }

    private function loadProfileRelations(): void
    {
        $this->member->loadMissing(['chapter', 'currentAssignments.position', 'positionAssignments.position', 'statusChanges']);
    }

    /**
     * Files linked to this member, newest first.
     *
     * @return Collection<int, Document>
     */
    #[Computed]
    public function documents(): Collection
    {
        return $this->member->documents()->with('folder')->latest('id')->get();
    }

    #[On('documents-uploaded')]
    public function refreshDocuments(): void
    {
        unset($this->documents);
    }

    #[On('position-assigned')]
    public function refreshPositions(): void
    {
        $this->member->load(['currentAssignments.position', 'positionAssignments.position']);
        unset($this->history);
    }

    /**
     * Save the chosen photo straight away (cropped square, location data removed).
     */
    public function updatedPhoto(MemberPhotos $photos): void
    {
        $this->authorize('update', $this->member);

        $this->photoError = '';

        $this->validate(
            ['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480']],
            ['photo.image' => 'Choose a JPG, PNG or WebP photo.', 'photo.mimes' => 'Choose a JPG, PNG or WebP photo.', 'photo.max' => 'Photos can be up to 20 MB.'],
        );

        try {
            $photos->store($this->member, $this->photo);
        } catch (RuntimeException) {
            $this->photoError = 'That photo couldn’t be read. Try a JPG or PNG.';
        } finally {
            $this->photo?->delete();
            $this->photo = null;
        }
    }

    /**
     * The browser couldn't send the photo (usually because it is too big).
     */
    public function photoUploadFailed(): void
    {
        $this->photoError = 'The photo couldn’t be uploaded. Photos can be up to 20 MB.';
    }

    public function removePhoto(MemberPhotos $photos): void
    {
        $this->authorize('update', $this->member);

        $photos->remove($this->member);
        $this->photoError = '';
    }

    /**
     * Save private notes when the field loses focus.
     */
    public function updatedPrivateNotes(): void
    {
        $this->authorize('update', $this->member);
        $this->authorize('viewPrivateNotes', $this->member);

        $this->validate(['privateNotes' => ['nullable', 'string', 'max:5000']]);

        $notes = trim($this->privateNotes);
        $this->member->forceFill(['private_notes' => $notes === '' ? null : $notes])->save();

        $this->notesSaved = true;
    }

    /**
     * All activities, newest recorded first.
     *
     * @return Collection<int, Activity>
     */
    #[Computed]
    public function activities(): Collection
    {
        return Activity::query()->orderByDesc('id')->get();
    }

    /**
     * @return array<int, AttendanceStatus> activity id => status (missing means absent)
     */
    #[Computed]
    public function attendance(): array
    {
        return $this->member->attendances()->whereHas('activity')->get()->mapWithKeys(fn (Attendance $attendance) => [$attendance->activity_id => $attendance->status])->all();
    }

    public function attendanceFor(Activity $activity): AttendanceStatus
    {
        return $this->attendance[$activity->id] ?? AttendanceStatus::Absent;
    }

    #[Computed]
    public function attendedCount(): int
    {
        return collect($this->attendance)->filter(fn (AttendanceStatus $status) => $status === AttendanceStatus::Present)->count();
    }

    /**
     * Position and record history, newest first: positions held, status changes and when the record was added.
     *
     * @return list<array{title: string, detail: string}>
     */
    #[Computed]
    public function history(): array
    {
        $format = fn (?CarbonImmutable $date): ?string => $date?->format('M j, Y');

        $items = collect();

        foreach ($this->member->positionAssignments as $assignment) {
            /** @var PositionAssignment $assignment */
            $label = $this->positionHistoryLabel($assignment);

            $items->push($assignment->ended_on === null
                ? ['date' => $assignment->started_on, 'title' => "Assigned {$label}", 'detail' => collect([$assignment->source, $format($assignment->started_on)])->filter()->implode(' · ')]
                : ['date' => $assignment->ended_on, 'title' => "Held {$label}", 'detail' => ($format($assignment->started_on) ?? 'Start not recorded').' – '.$format($assignment->ended_on)]);
        }

        foreach ($this->member->statusChanges as $change) {
            /** @var MemberStatusChange $change */
            $items->push([
                'date' => $change->changed_on,
                'title' => $change->from_status === null ? "Status set to {$change->to_status->label()}" : "Moved from {$change->from_status->label()} to {$change->to_status->label()}",
                'detail' => collect([$format($change->changed_on), $change->note])->filter()->implode(' · '),
            ]);
        }

        $items->push(['date' => $this->member->created_at?->toImmutable(), 'title' => 'Added to records', 'detail' => (string) $format($this->member->created_at?->toImmutable())]);

        $sorted = $items->sortByDesc(fn (array $item) => $item['date']?->timestamp ?? 0)->values();

        if ($this->member->positionAssignments->whereNotNull('ended_on')->isEmpty()) {
            $sorted->push(['date' => null, 'title' => 'Earlier positions', 'detail' => 'None recorded']);
        }

        return $sorted->map(fn (array $item) => ['title' => $item['title'], 'detail' => $item['detail']])->all();
    }

    private function positionHistoryLabel(PositionAssignment $assignment): string
    {
        $position = $assignment->position;

        return $position->branch === null ? $position->title : "{$position->code} · {$position->branch->label()} branch";
    }

    public function ageLabel(): string
    {
        if ($this->member->age !== null) {
            return (string) $this->member->age;
        }

        return $this->member->hasBirthday() ? 'Birth year missing' : 'Not recorded';
    }
};
?>

@php
    $positions = $member->currentPositions();
    $positionLongLabel = $positions->map->longLabel()->implode(', ') ?: 'No position yet';
    $summary = collect([
        $member->nickname ? '“'.$member->nickname.'”' : null,
        $member->chapter ? $member->chapter->name.' chapter' : null,
        $member->member_no ? 'Member no. '.$member->member_no : null,
    ])->filter()->implode(' · ');
    $total = $this->activities->count() - collect($this->attendance)->filter(fn ($status) => $status === App\Enums\AttendanceStatus::Exempt)->count();
    $percent = $total > 0 ? round($this->attendedCount / $total * 100) : 0;
@endphp

{{-- Designs: docs/design/web/Profile.dc.html (lg+), docs/design/mobile/Profile.dc.html (phones) --}}
<div class="contents">
    {{-- Phone top bar --}}
    <div class="-mx-4 flex items-center justify-between gap-2 bg-navy px-2 pt-[max(0.625rem,env(safe-area-inset-top))] pb-2.5 text-white lg:hidden">
        <a href="{{ route('members.index') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 rounded-lg px-2 text-[15px] font-semibold focus-visible:outline-2 focus-visible:outline-yellow">
            <x-icon name="chevron-left" :size="22" :stroke-width="2.2" />
            Members
        </a>
        @can('update', $member)
            <a href="{{ route('members.edit', $member) }}" wire:navigate class="inline-flex min-h-11 items-center rounded-[10px] px-3.5 text-[15px] font-semibold text-yellow focus-visible:outline-2 focus-visible:outline-yellow">Edit</a>
        @endcan
    </div>

    {{-- Desktop breadcrumb --}}
    <nav aria-label="Breadcrumb" class="hidden items-center gap-2 text-sm text-muted lg:flex">
        <a href="{{ route('members.index') }}" wire:navigate class="inline-flex min-h-11 items-center font-semibold text-blue hover:text-navy">Members</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ $member->full_name }}</span>
    </nav>

    {{-- Summary --}}
    <section aria-label="Member summary" class="-mx-4 -mt-4 flex flex-col items-center gap-2.5 rounded-b-3xl bg-navy px-4 pt-2 pb-6 text-center text-white lg:mx-0 lg:mt-0 lg:flex-row lg:flex-wrap lg:gap-6 lg:rounded-[20px] lg:p-7 lg:text-left">
        @php($photoUrl = $member->photoUrl())
        <div class="relative shrink-0" x-data="{ menu: false }" x-on:keydown.escape="menu = false" x-on:click.outside="menu = false">
            <x-avatar-initials :initials="$member->initials" :photo="$photoUrl" size="profile" />
            <div wire:loading.flex wire:target="photo,removePhoto" class="absolute inset-0 items-center justify-center rounded-full bg-navy/75 text-xs font-semibold text-white">Saving…</div>

            @can('update', $member)
                @php($cameraButton = 'absolute -right-2 -bottom-2 flex size-11 cursor-pointer items-center justify-center rounded-full focus-visible:outline-2 focus-visible:outline-yellow lg:-right-1 lg:-bottom-1')
                <input id="member-photo" type="file" accept="image/*" wire:model="photo" x-on:livewire-upload-error="$wire.photoUploadFailed()" class="sr-only" tabindex="-1">

                @if ($photoUrl)
                    <button type="button" x-on:click="menu = ! menu" aria-haspopup="true" x-bind:aria-expanded="menu" aria-label="Change or remove photo" class="{{ $cameraButton }}">
                        <span class="flex size-9 items-center justify-center rounded-full border-3 border-navy bg-yellow text-ink"><x-icon name="camera" :size="16" /></span>
                    </button>
                    <div x-show="menu" x-cloak class="absolute top-full left-1/2 z-20 mt-3 flex w-44 -translate-x-1/2 flex-col rounded-xl border border-line bg-white p-1 text-left text-sm font-semibold text-ink shadow-[0_8px_24px_rgba(22,24,29,0.18)]">
                        <label for="member-photo" role="button" tabindex="0" x-on:click="menu = false" x-on:keydown.enter.prevent="$el.click()" x-on:keydown.space.prevent="$el.click()" class="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-lg px-3 hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue">
                            <x-icon name="camera" :size="16" />
                            Change photo
                        </label>
                        <button type="button" wire:click="removePhoto" wire:confirm="Remove {{ $member->full_name }}’s photo?" x-on:click="menu = false" class="flex min-h-11 items-center gap-2.5 rounded-lg px-3 text-absent hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue">
                            <x-icon name="close" :size="16" />
                            Remove photo
                        </button>
                    </div>
                @else
                    <label for="member-photo" role="button" tabindex="0" aria-label="Add photo" x-on:keydown.enter.prevent="$el.click()" x-on:keydown.space.prevent="$el.click()" class="{{ $cameraButton }}">
                        <span class="flex size-9 items-center justify-center rounded-full border-3 border-navy bg-yellow text-ink"><x-icon name="camera" :size="16" /></span>
                    </label>
                @endif
            @endcan
        </div>

        <div class="flex min-w-0 flex-col items-center gap-2.5 lg:flex-[1_1_320px] lg:items-start">
            <div class="order-3 flex flex-wrap justify-center gap-2 lg:order-none">
                <x-status-pill :status="$member->status" />
                @forelse ($positions as $position)
                    <span class="rounded-full bg-yellow px-2.5 py-1 text-xs font-bold text-ink">{{ $position->shortLabel() }}</span>
                @empty
                    <span class="rounded-full bg-navy-2 px-2.5 py-1 text-xs font-semibold text-white">No position</span>
                @endforelse
            </div>
            <h1 class="order-1 font-display text-2xl leading-tight font-extrabold lg:order-none lg:text-[34px] lg:tracking-[-0.01em]">{{ $member->full_name }}</h1>
            @if ($summary !== '')
                <p class="order-2 text-sm text-navy-text lg:order-none lg:text-[15px]">{{ $summary }}</p>
            @endif
            @error('photo')
                <p role="alert" class="order-4 text-sm font-semibold text-[#FFD2BF] lg:order-none">{{ $message }}</p>
            @enderror
            @if ($photoError !== '')
                <p role="alert" class="order-4 text-sm font-semibold text-[#FFD2BF] lg:order-none">{{ $photoError }}</p>
            @endif
        </div>

        {{-- Desktop actions --}}
        <div class="hidden flex-wrap gap-2.5 lg:flex">
            <button type="button" wire:click="$dispatch('open-assign-position', { memberId: {{ $member->id }} })" class="inline-flex min-h-11 items-center gap-2 rounded-[10px] border border-[#4A55A0] px-4 text-sm font-semibold text-white hover:bg-navy-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow">
                <x-icon name="swap" :size="16" />
                Change position
            </button>
            @can('update', $member)
                <a href="{{ route('members.edit', $member) }}" wire:navigate class="inline-flex min-h-11 items-center gap-2 rounded-[10px] bg-yellow px-[18px] text-sm font-bold text-ink hover:bg-[#FBE27A] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow">
                    <x-icon name="pencil" :size="16" />
                    Edit profile
                </a>
            @endcan
        </div>

        {{-- Phone quick actions --}}
        <div class="mt-1.5 grid w-full grid-cols-3 gap-2 lg:hidden">
            <a href="{{ route('activities') }}" wire:navigate class="flex min-h-16 flex-col items-center justify-center gap-1 rounded-xl bg-navy-2 text-xs font-semibold text-white focus-visible:outline-2 focus-visible:outline-yellow">
                <x-icon name="activities" :size="20" />
                Attendance
            </a>
            <button type="button" wire:click="$dispatch('open-assign-position', { memberId: {{ $member->id }} })" class="flex min-h-16 flex-col items-center justify-center gap-1 rounded-xl bg-navy-2 text-xs font-semibold text-white focus-visible:outline-2 focus-visible:outline-yellow">
                <x-icon name="org" :size="20" />
                Position
            </button>
            <label for="document-file" role="button" tabindex="0" x-on:keydown.enter.prevent="$el.click()" x-on:keydown.space.prevent="$el.click()" class="flex min-h-16 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl bg-navy-2 text-xs font-semibold text-white focus-visible:outline-2 focus-visible:outline-yellow">
                <x-icon name="upload" :size="20" />
                Upload
            </label>
        </div>
    </section>

    <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-start lg:gap-5">
        {{-- Left column (desktop) --}}
        <div class="contents lg:flex lg:min-w-0 lg:flex-[1_1_360px] lg:flex-col lg:gap-5">
            {{-- Personal information: desktop --}}
            <x-card class="hidden flex-col gap-4 p-6 lg:flex">
                <h2 class="text-lg font-bold">Personal information</h2>
                <dl class="grid grid-cols-2 gap-4">
                    @foreach ([
                        'Full name' => $member->full_name,
                        'Nickname' => $member->nickname ?? 'Not recorded',
                        'Birthday' => $member->birthday_label ?? 'Not recorded',
                    ] as $term => $value)
                        <div class="flex flex-col gap-[3px]"><dt class="text-xs text-muted">{{ $term }}</dt><dd class="text-[15px] font-semibold">{{ $value }}</dd></div>
                    @endforeach
                    <div class="flex flex-col gap-[3px]">
                        <dt class="text-xs text-muted">Age</dt>
                        <dd class="text-[15px] font-semibold">{{ $this->ageLabel() }}@if ($member->age !== null) <span class="text-xs font-medium text-muted">(auto-calculated)</span>@endif</dd>
                    </div>
                    <div class="flex flex-col gap-[3px]"><dt class="text-xs text-muted">Grade level</dt><dd class="text-[15px] font-semibold">{{ $member->grade_level ?? 'Not recorded' }}</dd></div>
                    <div class="flex flex-col gap-[3px]"><dt class="text-xs text-muted">Chapter</dt><dd class="text-[15px] font-semibold">{{ $member->chapter?->name ?? 'Not recorded' }}</dd></div>
                </dl>
            </x-card>

            {{-- Membership: desktop --}}
            <x-card class="hidden flex-col gap-4 p-6 lg:flex">
                <h2 class="text-lg font-bold">Membership</h2>
                <dl class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-[3px]"><dt class="text-xs text-muted">Member no.</dt><dd class="text-[15px] font-semibold">{{ $member->member_no ?? 'Not recorded' }}</dd></div>
                    <div class="flex flex-col gap-[3px]"><dt class="text-xs text-muted">Status</dt><dd class="text-[15px] font-semibold">{{ $member->status->label() }}</dd></div>
                    <div class="flex flex-col gap-[3px]"><dt class="text-xs text-muted">Position</dt><dd class="text-[15px] font-semibold">{{ $positionLongLabel }}</dd></div>
                    <div class="flex flex-col gap-[3px]">
                        <dt class="text-xs text-muted">Date joined</dt>
                        @if ($member->joined_on)
                            <dd class="text-[15px] font-semibold">{{ $member->joined_on->format('M j, Y') }}</dd>
                        @else
                            <dd class="text-[15px] font-semibold text-[#8A5300]">Not recorded @can('update', $member)· <a href="{{ route('members.edit', $member) }}#joined_on" wire:navigate class="text-blue hover:text-navy">Add</a>@endcan</dd>
                        @endif
                    </div>
                    @if ($member->remarks)
                        <div class="col-span-2 flex flex-col gap-[3px]"><dt class="text-xs text-muted">Founder’s remark</dt><dd class="text-[15px] font-semibold">{{ $member->remarks }}</dd></div>
                    @endif
                </dl>
            </x-card>

            {{-- Personal information: phone --}}
            <x-card class="order-1 px-4 py-1.5 lg:hidden">
                <h2 class="pt-3 pb-1.5 text-base font-bold">Personal information</h2>
                <dl>
                    @foreach ([
                        'Birthday' => $member->birthday_label ?? 'Not recorded',
                        'Grade level' => $member->grade_level ?? 'Not recorded',
                        'Chapter' => $member->chapter?->name ?? 'Not recorded',
                        'Position' => $positionLongLabel,
                    ] as $term => $value)
                        @if ($loop->index === 1)
                            <div class="flex justify-between gap-3 border-t border-line-soft py-3">
                                <dt class="text-sm text-muted">Age</dt>
                                <dd class="text-right text-sm font-semibold">{{ $this->ageLabel() }}@if ($member->age !== null) <span class="font-normal text-muted">(auto)</span>@endif</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-3 border-t border-line-soft py-3"><dt class="text-sm text-muted">{{ $term }}</dt><dd class="text-right text-sm font-semibold">{{ $value }}</dd></div>
                    @endforeach
                    <div class="flex items-center justify-between gap-3 border-t border-line-soft py-2">
                        <dt class="text-sm text-muted">Date joined</dt>
                        <dd class="text-right">
                            @if ($member->joined_on)
                                <span class="text-sm font-semibold">{{ $member->joined_on->format('M j, Y') }}</span>
                            @else
                                @can('update', $member)
                                    <a href="{{ route('members.edit', $member) }}#joined_on" wire:navigate class="inline-flex min-h-11 items-center rounded-lg border border-dashed border-[#B9B6A8] bg-white px-3 text-[13px] font-semibold text-blue">Add date</a>
                                @else
                                    <span class="text-sm font-semibold">Not recorded</span>
                                @endcan
                            @endif
                        </dd>
                    </div>
                    @if ($member->remarks)
                        <div class="flex justify-between gap-3 border-t border-line-soft py-3"><dt class="text-sm text-muted">Founder’s remark</dt><dd class="text-right text-sm font-semibold">{{ $member->remarks }}</dd></div>
                    @endif
                </dl>
            </x-card>

            {{-- Private notes --}}
            @can('viewPrivateNotes', $member)
                <section aria-labelledby="notes-heading" class="order-4 flex flex-col gap-2 rounded-2xl border border-[#F0E2A8] bg-[#FFFBEA] p-4 lg:order-none lg:gap-3 lg:border-line lg:bg-white lg:p-6">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 id="notes-heading" class="text-base font-bold lg:text-lg">Private notes</h2>
                        <span wire:loading.remove wire:target="privateNotes" class="text-xs text-muted" role="status">{{ $notesSaved ? 'Saved' : '' }}</span>
                        <span wire:loading wire:target="privateNotes" class="text-xs text-muted">Saving…</span>
                    </div>
                    <label for="private-notes" class="text-xs text-muted lg:text-[13px]">Only you can see these notes</label>
                    <textarea
                        id="private-notes"
                        rows="4"
                        wire:model.blur="privateNotes"
                        placeholder="Write a note about {{ Str::before($member->full_name, ' ') }}…"
                        class="resize-y rounded-[10px] border border-[#E4D9A8] bg-white px-3 py-2.5 text-sm text-ink focus-visible:outline-2 focus-visible:outline-blue lg:border-line-input lg:bg-[#FAF9F4] lg:p-3"
                    ></textarea>
                    @error('privateNotes') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                </section>
            @endcan
        </div>

        {{-- Right column (desktop) --}}
        <div class="contents lg:flex lg:min-w-0 lg:flex-[1_1_400px] lg:flex-col lg:gap-5">
            {{-- Attendance --}}
            <x-card class="order-2 flex flex-col gap-3 p-4 lg:order-none lg:gap-3.5 lg:p-6">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-base font-bold lg:text-lg">Attendance</h2>
                    <p class="text-sm text-muted">
                        <strong class="font-display text-[22px] font-extrabold text-ink">{{ $this->attendedCount }}</strong>
                        of {{ $total }}<span class="hidden lg:inline"> activities</span>
                    </p>
                </div>
                <span class="hidden h-2 rounded-full bg-inactive-bg lg:block" aria-hidden="true"><span class="block h-2 rounded-full bg-blue" style="width: {{ $percent }}%"></span></span>
                <div class="grid gap-1.5 lg:hidden" style="grid-template-columns: repeat({{ max($total, 1) }}, minmax(0, 1fr))" aria-hidden="true">
                    @foreach ($this->activities->reverse() as $activity)
                        <span wire:key="segment-{{ $activity->id }}" @class(['h-2 rounded-full', 'bg-blue' => $this->attendanceFor($activity) === App\Enums\AttendanceStatus::Present, 'bg-[#DDF1F2]' => $this->attendanceFor($activity) === App\Enums\AttendanceStatus::Exempt, 'bg-[#E2E0D6]' => ! in_array($this->attendanceFor($activity), [App\Enums\AttendanceStatus::Present, App\Enums\AttendanceStatus::Exempt], true)])></span>
                    @endforeach
                </div>
                <ul>
                    @foreach ($this->activities as $activity)
                        @php($mark = $this->attendanceFor($activity))
                        <li wire:key="activity-{{ $activity->id }}" class="flex items-center justify-between gap-3 border-t border-line-soft py-2.5 lg:border-t-0 lg:border-b">
                            <span class="flex min-w-0 flex-col gap-0.5">
                                <span class="text-sm font-semibold">{{ $activity->display_name }}</span>
                                <span class="text-xs text-muted">{{ $activity->date_label }}</span>
                            </span>
                            <span @class([
                                'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-info-bg text-info-fg' => $mark === App\Enums\AttendanceStatus::Present,
                                'bg-inactive-bg text-inactive-fg' => $mark === App\Enums\AttendanceStatus::Excused,
                                'bg-[#F3F2EC] text-muted' => $mark === App\Enums\AttendanceStatus::Absent,
                                'bg-[#DDF1F2] text-[#0A5F63]' => $mark === App\Enums\AttendanceStatus::Exempt,
                            ])>{{ $mark === App\Enums\AttendanceStatus::Present ? 'Attended' : $mark->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{-- Position & record history --}}
            <x-card class="order-3 flex flex-col gap-3.5 p-4 lg:order-none lg:p-6">
                <h2 class="text-base font-bold lg:text-lg">Position &amp; record history</h2>
                <ol>
                    @foreach ($this->history as $item)
                        <li class="flex gap-3.5">
                            <span class="flex w-3.5 shrink-0 flex-col items-center">
                                <span @class([
                                    'mt-[3px] box-border size-3.5 shrink-0 rounded-full border-3',
                                    'border-navy bg-yellow' => $loop->first,
                                    'border-[#B9B6A8] bg-white' => ! $loop->first,
                                ])></span>
                                @unless ($loop->last)
                                    <span class="min-h-[18px] w-0.5 flex-1 bg-line" aria-hidden="true"></span>
                                @endunless
                            </span>
                            <span class="flex flex-col gap-[3px] pb-4">
                                <span class="text-sm font-semibold">{{ $item['title'] }}</span>
                                <span class="text-xs text-muted lg:text-[13px]">{{ $item['detail'] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </x-card>

            {{-- Documents & photos --}}
            <x-card class="order-5 flex flex-col gap-3.5 p-4 lg:order-none lg:p-6">
                <h2 class="text-base font-bold lg:text-lg">Documents &amp; photos</h2>
                @if ($this->documents->isNotEmpty())
                    <ul class="flex flex-col">
                        @foreach ($this->documents as $document)
                            <li wire:key="document-{{ $document->id }}" class="flex items-center gap-3 border-t border-line-soft py-2.5 first:border-t-0">
                                @if ($document->isPreviewableImage())
                                    <img src="{{ route('documents.preview', $document) }}" alt="" loading="lazy" class="size-11 shrink-0 rounded-lg bg-[#F0EFE8] object-cover">
                                @else
                                    <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-info-bg text-[11px] font-extrabold tracking-[0.04em] text-info-fg">{{ $document->extension() }}</span>
                                @endif
                                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span class="truncate text-sm font-semibold">{{ $document->original_name }}</span>
                                    <span class="truncate text-xs text-muted">{{ $document->folder->name }} · {{ $document->sizeLabel() }} · {{ $document->created_at->format('M j, Y') }}</span>
                                </span>
                                <a href="{{ route('documents.download', $document) }}" class="inline-flex min-h-11 shrink-0 items-center px-2 text-[13px] font-semibold text-blue hover:text-navy">Download<span class="sr-only"> {{ $document->original_name }}</span></a>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @can('create', App\Models\Document::class)
                    <label for="document-file" role="button" tabindex="0" x-on:keydown.enter.prevent="$el.click()" x-on:keydown.space.prevent="$el.click()" class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-[#CFCCC0] bg-[#FAF9F4] px-4 py-6 text-center hover:border-blue focus-visible:outline-2 focus-visible:outline-blue lg:py-7">
                        <x-icon name="upload" :size="24" class="text-muted" />
                        <span class="text-sm font-semibold">Upload files for {{ Str::before($member->full_name, ' ') }}</span>
                        <span class="text-[13px] text-muted">Photos, forms, certificates · stored privately</span>
                    </label>
                @endcan
            </x-card>
        </div>
    </div>

    <livewire:assign-position />
    <livewire:document-upload :default-member-id="$member->id" />
</div>
