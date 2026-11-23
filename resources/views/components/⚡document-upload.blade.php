<?php

use App\Models\Activity;
use App\Models\Document;
use App\Models\Folder;
use App\Models\Member;
use App\Services\DocumentStore;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Upload pop-up, shared by Documents & photos and the member profile.
 *
 * Its hidden inputs (#document-camera, #document-file) can be triggered from anywhere on the page with
 * <label for="...">, so the phone's camera opens straight away. Open the drag-and-drop pop-up with
 * $dispatch('open-document-upload'). Emits "documents-uploaded" after saving.
 */
new class extends Component
{
    use WithFileUploads;

    /** Defaults from the page: the folder being viewed, or the member whose profile this is. */
    #[Reactive]
    public ?int $defaultFolderId = null;

    public ?int $defaultMemberId = null;

    public bool $open = false;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $uploads = [];

    public ?int $folderId = null;

    public ?int $memberId = null;

    public ?int $activityId = null;

    public string $note = '';

    /** Shown when the browser upload itself failed (too big for the 20 MB or server limit). */
    public string $uploadError = '';

    #[On('open-document-upload')]
    public function openDialog(): void
    {
        $this->authorize('create', Document::class);
        $this->resetDetails();
        $this->open = true;
    }

    /**
     * Picking files (camera, file picker or drag-and-drop) opens the pop-up to add the details.
     */
    public function updatedUploads(): void
    {
        if (! $this->open) {
            $this->resetDetails(keepUploads: true);
        }

        $this->uploadError = '';
        $this->open = true;
        $this->validateOnly('uploads.*');
    }

    /**
     * The browser could not send a file (usually because it is too big): open the pop-up and say so.
     */
    public function uploadFailed(): void
    {
        if (! $this->open) {
            $this->resetDetails();
        }

        $this->uploadError = 'A file couldn’t be uploaded. Files can be up to 20 MB each.';
        $this->open = true;
    }

    public function removeUpload(int $index): void
    {
        unset($this->uploads[$index]);
        $this->uploads = array_values($this->uploads);
    }

    public function close(): void
    {
        $this->open = false;
        $this->uploads = [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'uploads' => ['required', 'array', 'min:1', 'max:20'],
            'uploads.*' => ['file', 'max:'.Document::MAX_KILOBYTES, 'mimes:'.implode(',', Document::ALLOWED_EXTENSIONS)],
            'folderId' => ['required', 'integer', Rule::exists('folders', 'id')],
            'memberId' => ['nullable', 'integer', Rule::exists('members', 'id')->whereNull('deleted_at')],
            'activityId' => ['nullable', 'integer', Rule::exists('activities', 'id')->whereNull('deleted_at')],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'uploads.required' => 'Choose at least one file.',
            'uploads.*.max' => 'Files can be up to 20 MB.',
            'uploads.*.mimes' => 'Only photos, PDF, Word and Excel files can be uploaded.',
            'uploads.*.uploaded' => 'This file didn’t finish uploading. Files can be up to 20 MB.',
        ];
    }

    public function save(DocumentStore $store): void
    {
        $this->authorize('create', Document::class);
        $this->validate();

        foreach ($this->uploads as $upload) {
            $store->store($upload, [
                'folder_id' => $this->folderId,
                'member_id' => $this->memberId,
                'activity_id' => $this->activityId,
                'note' => trim($this->note) === '' ? null : trim($this->note),
            ]);
        }

        $count = count($this->uploads);
        $this->close();
        $this->dispatch('documents-uploaded', count: $count);
    }

    private function resetDetails(bool $keepUploads = false): void
    {
        $this->resetValidation();
        $this->uploadError = '';

        if (! $keepUploads) {
            $this->uploads = [];
        }

        $this->note = '';
        $this->activityId = null;
        $this->memberId = $this->defaultMemberId;
        $this->folderId = $this->defaultFolderId
            ?? Folder::query()->where('name', $this->defaultMemberId ? 'Member Documents' : 'Activity Photos')->value('id')
            ?? Folder::query()->orderBy('id')->value('id');
    }

    /**
     * @return Collection<int, Folder>
     */
    #[Computed]
    public function folders(): Collection
    {
        return Folder::query()->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Member>
     */
    #[Computed]
    public function members(): Collection
    {
        return Member::query()->orderBy('full_name')->get(['id', 'full_name']);
    }

    /**
     * @return Collection<int, Activity>
     */
    #[Computed]
    public function activities(): Collection
    {
        return Activity::query()->orderByDesc('id')->get();
    }
};
?>

@php
    $input = 'min-h-11 w-full rounded-[10px] border border-line-input bg-white px-3 text-[15px] text-ink focus-visible:outline-2 focus-visible:outline-blue aria-invalid:border-absent';
    $accept = collect(App\Models\Document::ALLOWED_EXTENSIONS)->map(fn ($extension) => '.'.$extension)->implode(',');
@endphp

<div>
    {{-- Hidden pickers, opened by <label for="document-camera"> / <label for="document-file"> anywhere on the page --}}
    <input id="document-camera" type="file" multiple accept="image/*" capture="environment" wire:model="uploads" x-on:livewire-upload-error="$wire.uploadFailed()" class="sr-only" tabindex="-1">
    <input id="document-file" type="file" multiple accept="{{ $accept }}" wire:model="uploads" x-on:livewire-upload-error="$wire.uploadFailed()" class="sr-only" tabindex="-1">

    @if ($open)
        {{-- wire:ignore.self keeps Livewire from stripping the dialog's "open" state when the form re-renders. --}}
        <dialog
            wire:ignore.self
            x-data
            x-init="$el.showModal()"
            x-on:close="$wire.close()"
            aria-labelledby="upload-heading"
            class="m-auto w-[min(34rem,calc(100%-2rem))] rounded-2xl border border-line bg-white p-0 text-ink shadow-xl backdrop:bg-ink/50"
        >
            <form wire:submit="save" class="flex flex-col gap-4 p-5 lg:p-6" novalidate>
                <div class="flex items-start justify-between gap-3">
                    <div class="flex flex-col gap-1">
                        <h2 id="upload-heading" class="font-display text-[22px] font-extrabold">Upload</h2>
                        <p class="text-[13px] text-muted">Photos, PDF, Word and Excel · up to 20 MB each · stored privately.</p>
                    </div>
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue" aria-label="Close">
                        <x-icon name="close" :size="20" />
                    </button>
                </div>

                {{-- Drag and drop: dropping files onto the (invisible) file input works natively --}}
                <label class="relative flex cursor-pointer flex-col items-center gap-1.5 rounded-xl border-2 border-dashed border-[#CFCCC0] bg-[#FAF9F4] px-4 py-6 text-center hover:border-blue has-focus-visible:outline-2 has-focus-visible:outline-blue">
                    <x-icon name="upload" :size="24" class="text-muted" />
                    <span class="text-sm font-semibold">Drop files here or choose files</span>
                    <span class="text-[13px] text-muted">You can pick several at once.</span>
                    <input type="file" multiple accept="{{ $accept }}" wire:model="uploads" x-on:livewire-upload-error="$wire.uploadFailed()" class="absolute inset-0 cursor-pointer opacity-0" aria-label="Choose files to upload">
                </label>
                <p wire:loading wire:target="uploads" class="text-sm text-muted" role="status">Uploading…</p>

                @if ($uploads !== [])
                    <ul class="flex flex-col divide-y divide-line-soft rounded-xl border border-line">
                        @foreach ($uploads as $index => $upload)
                            <li wire:key="upload-{{ $index }}-{{ $upload->getFilename() }}" class="flex items-center gap-3 px-3 py-2">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold">{{ $upload->getClientOriginalName() }}</span>
                                    <span class="block text-xs text-muted">{{ App\Models\Document::formatBytes((int) $upload->getSize()) }}</span>
                                    @error('uploads.'.$index) <span class="block text-xs text-absent">{{ $message }}</span> @enderror
                                </span>
                                <button type="button" wire:click="removeUpload({{ $index }})" class="flex size-11 shrink-0 items-center justify-center rounded-lg text-muted hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue" aria-label="Remove {{ $upload->getClientOriginalName() }}">
                                    <x-icon name="close" :size="16" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($uploadError !== '')
                    <p class="text-sm text-absent" role="alert">{{ $uploadError }}</p>
                @endif
                @error('uploads') <p class="text-sm text-absent">{{ $message }}</p> @enderror

                <div class="flex flex-col gap-1.5">
                    <label for="upload-folder" class="text-sm font-semibold">Folder</label>
                    <select id="upload-folder" wire:model="folderId" class="{{ $input }}">
                        @foreach ($this->folders as $folder)
                            <option value="{{ $folder->id }}">{{ $folder->name }}</option>
                        @endforeach
                    </select>
                    @error('folderId') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="upload-member" class="text-sm font-semibold">Member <span class="font-normal text-muted">(optional)</span></label>
                        <select id="upload-member" wire:model="memberId" class="{{ $input }}">
                            <option value="">Not linked</option>
                            @foreach ($this->members as $member)
                                <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="upload-activity" class="text-sm font-semibold">Activity <span class="font-normal text-muted">(optional)</span></label>
                        <select id="upload-activity" wire:model="activityId" class="{{ $input }}">
                            <option value="">Not linked</option>
                            @foreach ($this->activities as $activity)
                                <option value="{{ $activity->id }}">{{ $activity->display_name }} · {{ $activity->date_label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="upload-note" class="text-sm font-semibold">Note <span class="font-normal text-muted">(optional)</span></label>
                    <textarea id="upload-note" rows="2" wire:model="note" class="{{ $input }} py-2"></textarea>
                    @error('note') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="min-h-11 rounded-[10px] border border-line-input bg-white px-5 text-sm font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="uploads,save" class="min-h-11 rounded-[10px] bg-blue px-6 text-sm font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue disabled:opacity-60">
                        Save {{ count($uploads) > 1 ? count($uploads).' files' : 'file' }}
                    </button>
                </div>
            </form>
        </dialog>
    @endif
</div>
