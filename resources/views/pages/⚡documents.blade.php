<?php

use App\Models\Activity;
use App\Models\Document;
use App\Models\Folder;
use App\Models\Member;
use App\Services\DocumentStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Documents & photos')] class extends Component
{
    /** Folder being viewed; null = all files. */
    #[Url(as: 'folder', except: null)]
    public ?int $folderId = null;

    public ?int $selectedId = null;

    /** Phones: file details sheet. */
    public bool $sheetOpen = false;

    public bool $editOpen = false;

    public string $editName = '';

    public ?int $editFolderId = null;

    public ?int $editMemberId = null;

    public ?int $editActivityId = null;

    public string $editNote = '';

    public bool $folderFormOpen = false;

    public string $newFolderName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Document::class);
    }

    #[On('documents-uploaded')]
    public function refreshFiles(): void
    {
        unset($this->folders, $this->files, $this->totals);
    }

    /**
     * @return Collection<int, Folder>
     */
    #[Computed]
    public function folders(): Collection
    {
        return Folder::query()->withCount('documents')->orderBy('id')->get();
    }

    /**
     * Files in the chosen folder (or all), newest first.
     *
     * @return Collection<int, Document>
     */
    #[Computed]
    public function files(): Collection
    {
        return Document::query()
            ->with(['folder', 'member', 'activity'])
            ->when($this->folderId !== null, fn ($query) => $query->where('folder_id', $this->folderId))
            ->latest('id')
            ->get();
    }

    /**
     * @return array{count: int, bytes: int}
     */
    #[Computed]
    public function totals(): array
    {
        return ['count' => Document::query()->count(), 'bytes' => (int) Document::query()->sum('size')];
    }

    #[Computed]
    public function selected(): ?Document
    {
        return $this->files->firstWhere('id', $this->selectedId) ?? $this->files->first();
    }

    public function showFolder(?int $folderId): void
    {
        $this->folderId = $folderId;
        $this->selectedId = null;
        unset($this->files, $this->selected);
    }

    public function select(int $documentId): void
    {
        $this->selectedId = $documentId;
    }

    public function openSheet(int $documentId): void
    {
        $this->selectedId = $documentId;
        $this->sheetOpen = true;
    }

    public function closeSheet(): void
    {
        $this->sheetOpen = false;
    }

    public function openEdit(): void
    {
        $document = $this->selected;
        abort_if($document === null, 404);
        $this->authorize('update', $document);

        $this->resetValidation();
        $this->editName = $document->original_name;
        $this->editFolderId = $document->folder_id;
        $this->editMemberId = $document->member_id;
        $this->editActivityId = $document->activity_id;
        $this->editNote = (string) $document->note;
        $this->sheetOpen = false;
        $this->editOpen = true;
    }

    public function closeEdit(): void
    {
        $this->editOpen = false;
    }

    /**
     * Rename, move to another folder, link to a member / activity, or change the note.
     */
    public function saveEdit(): void
    {
        $document = $this->selected;
        abort_if($document === null, 404);
        $this->authorize('update', $document);

        $this->validate([
            'editName' => ['required', 'string', 'max:200'],
            'editFolderId' => ['required', 'integer', Rule::exists('folders', 'id')],
            'editMemberId' => ['nullable', 'integer', Rule::exists('members', 'id')->whereNull('deleted_at')],
            'editActivityId' => ['nullable', 'integer', Rule::exists('activities', 'id')->whereNull('deleted_at')],
            'editNote' => ['nullable', 'string', 'max:500'],
        ], [], ['editName' => 'name', 'editFolderId' => 'folder']);

        $name = app(DocumentStore::class)->cleanName($this->editName);
        $extension = pathinfo($document->original_name, PATHINFO_EXTENSION);

        if ($extension !== '' && ! str_ends_with(Str::lower($name), '.'.Str::lower($extension))) {
            $name .= '.'.$extension;
        }

        $document->update([
            'original_name' => $name,
            'folder_id' => $this->editFolderId,
            'member_id' => $this->editMemberId,
            'activity_id' => $this->editActivityId,
            'note' => trim($this->editNote) === '' ? null : trim($this->editNote),
        ]);

        $this->editOpen = false;
        unset($this->folders, $this->files, $this->selected);
    }

    public function deleteSelected(DocumentStore $store): void
    {
        $document = $this->selected;
        abort_if($document === null, 404);
        $this->authorize('delete', $document);

        $store->delete($document);

        $this->selectedId = null;
        $this->sheetOpen = false;
        unset($this->folders, $this->files, $this->selected, $this->totals);
    }

    public function openFolderForm(): void
    {
        $this->authorize('create', Document::class);
        $this->resetValidation();
        $this->newFolderName = '';
        $this->folderFormOpen = true;
    }

    public function closeFolderForm(): void
    {
        $this->folderFormOpen = false;
    }

    public function createFolder(): void
    {
        $this->authorize('create', Document::class);

        $this->validate(
            ['newFolderName' => ['required', 'string', 'max:60', Rule::unique('folders', 'name')]],
            ['newFolderName.unique' => 'There is already a folder with this name.'],
            ['newFolderName' => 'folder name'],
        );

        $folder = Folder::create(['name' => trim($this->newFolderName)]);

        $this->folderFormOpen = false;
        $this->showFolder($folder->id);
        unset($this->folders);
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

    public function countLabel(int $count): string
    {
        return $count === 0 ? 'Empty' : $count.' '.Str::plural('file', $count);
    }
};
?>

@php
    $badgeTone = fn (App\Models\Document $document) => match (true) {
        in_array($document->extension(), ['XLSX', 'XLS', 'CSV'], true) => 'bg-active-bg text-active-fg',
        in_array($document->extension(), ['PDF'], true) => 'bg-alert-bg text-alert-fg',
        default => 'bg-info-bg text-info-fg',
    };
    $currentFolder = $this->folders->firstWhere('id', $folderId);
    $selected = $this->selected;
    $input = 'min-h-11 w-full rounded-[10px] border border-line-input bg-white px-3 text-[15px] text-ink focus-visible:outline-2 focus-visible:outline-blue aria-invalid:border-absent';
    $folderTiles = collect([['id' => null, 'name' => 'All files', 'count' => $this->totals['count']]])
        ->concat($this->folders->map(fn ($folder) => ['id' => $folder->id, 'name' => $folder->name, 'count' => $folder->documents_count]));
@endphp

{{-- Designs: docs/design/web/Documents.dc.html (lg+), docs/design/mobile/Documents.dc.html (phones) --}}
<div class="contents">
    <x-page-header title="Documents & photos" :subtitle="$this->totals['count'].' '.Str::plural('file', $this->totals['count']).' · '.App\Models\Document::formatBytes($this->totals['bytes']).' · every file can be linked to a member or an activity'" back-to-more>
        @can('create', App\Models\Document::class)
            <div class="hidden flex-wrap gap-2.5 lg:flex">
                <button type="button" wire:click="openFolderForm" class="inline-flex min-h-11 items-center gap-2 rounded-[10px] border border-line-input bg-white px-4 text-sm font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue">
                    <x-icon name="documents" :size="16" />New folder
                </button>
                <button type="button" x-on:click="$dispatch('open-document-upload')" class="inline-flex min-h-11 items-center gap-2 rounded-[10px] bg-blue px-[18px] text-sm font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                    <x-icon name="upload" :size="16" />Upload
                </button>
            </div>
        @endcan
    </x-page-header>

    {{-- Folders --}}
    <section aria-labelledby="folders-heading" class="flex flex-col gap-2">
        <h2 id="folders-heading" class="px-1 text-xs font-bold tracking-[0.08em] text-muted uppercase lg:sr-only">Folders</h2>
        <div class="grid grid-cols-2 gap-2.5 lg:flex lg:flex-wrap lg:gap-3">
            @foreach ($folderTiles as $tile)
                @php($on = $folderId === $tile['id'])
                <button type="button" wire:key="folder-{{ $tile['id'] ?? 'all' }}" wire:click="showFolder({{ $tile['id'] ?? 'null' }})" aria-pressed="{{ $on ? 'true' : 'false' }}" @class([
                    'flex min-h-23 flex-col items-start gap-1.5 rounded-[14px] border p-3.5 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue lg:min-h-0 lg:flex-[1_1_150px] lg:px-4',
                    'border-navy bg-navy text-white' => $on,
                    'border-line bg-white text-ink hover:border-line-input' => ! $on,
                ])>
                    <svg aria-hidden="true" width="26" height="22" viewBox="0 0 26 22"><path d="M1 4a3 3 0 0 1 3-3h6l3 3h9a3 3 0 0 1 3 3v11a3 3 0 0 1-3 3H4a3 3 0 0 1-3-3z" fill="{{ $on ? '#F5D547' : '#FFF6D1' }}" stroke="{{ $on ? '#F5D547' : '#C9A400' }}" stroke-width="1.5"></path></svg>
                    <span class="text-sm font-semibold lg:font-bold">{{ $tile['name'] }}</span>
                    <span @class(['text-xs', 'text-navy-text' => $on, 'text-muted' => ! $on])>{{ $this->countLabel($tile['count']) }}</span>
                </button>
            @endforeach
        </div>
    </section>

    <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-start lg:gap-5">
        {{-- ───────────── Phone file list ───────────── --}}
        <section aria-labelledby="phone-files-heading" class="flex flex-col gap-2 lg:hidden">
            <h2 id="phone-files-heading" class="px-1 text-xs font-bold tracking-[0.08em] text-muted uppercase">{{ $currentFolder?->name ?? 'Recent files' }}</h2>
            @if ($this->files->isEmpty())
                <div class="flex flex-col items-center gap-1.5 rounded-2xl border-[1.5px] border-dashed border-[#C9C5B4] bg-white px-4 py-7 text-center">
                    <p class="text-[15px] font-semibold">{{ $currentFolder ? 'This folder is empty' : 'No files yet' }}</p>
                    <p class="text-[13px] text-muted">Add photos from your phone or files from your drive.</p>
                </div>
            @else
                <ul class="overflow-hidden rounded-2xl border border-line bg-white">
                    @foreach ($this->files as $document)
                        <li wire:key="phone-file-{{ $document->id }}" class="flex min-h-[68px] items-center gap-3 border-b border-line-soft px-3.5 py-2.5 last:border-b-0">
                            @if ($document->isPreviewableImage())
                                <img src="{{ route('documents.preview', $document) }}" alt="" loading="lazy" class="size-11 shrink-0 rounded-lg bg-[#F0EFE8] object-cover">
                            @else
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-lg text-[11px] font-extrabold tracking-[0.04em] {{ $badgeTone($document) }}">{{ $document->extension() }}</span>
                            @endif
                            <button type="button" wire:click="openSheet({{ $document->id }})" class="flex min-w-0 flex-1 flex-col gap-[3px] text-left focus-visible:outline-2 focus-visible:outline-blue">
                                <span class="truncate text-sm font-semibold">{{ $document->original_name }}</span>
                                <span class="truncate text-xs text-muted">{{ $document->folder->name }} · {{ $document->sizeLabel() }} · {{ $document->created_at->format('M j, Y') }}</span>
                            </button>
                            <button type="button" wire:click="openSheet({{ $document->id }})" class="flex size-11 shrink-0 items-center justify-center rounded-[10px] text-muted focus-visible:outline-2 focus-visible:outline-blue" aria-label="Options for {{ $document->original_name }}">
                                <x-icon name="more-vertical" :size="20" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- ───────────── Desktop file table ───────────── --}}
        <section aria-label="Files" class="hidden min-w-0 overflow-hidden rounded-2xl border border-line bg-white lg:block lg:flex-[999_1_600px]">
            <div class="overflow-x-auto">
                <div class="min-w-[640px]">
                    <div class="grid grid-cols-[minmax(240px,2.6fr)_1.2fr_80px_120px] gap-3 border-b border-line bg-[#FAF9F4] px-5 py-3 text-xs font-bold tracking-[0.06em] text-muted uppercase">
                        <span>Name</span><span>Folder</span><span>Size</span><span>Added</span>
                    </div>
                    @foreach ($this->files as $document)
                        @php($on = $selected?->is($document))
                        <button type="button" wire:key="file-{{ $document->id }}" wire:click="select({{ $document->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}" @class([
                            'grid min-h-16 w-full grid-cols-[minmax(240px,2.6fr)_1.2fr_80px_120px] items-center gap-3 border-b border-line-soft px-5 py-2.5 text-left text-ink focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-blue',
                            'bg-[#EEF1FC] shadow-[inset_4px_0_0_var(--color-blue)]' => $on,
                            'bg-white hover:bg-[#FAF9F4]' => ! $on,
                        ])>
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-lg text-[11px] font-extrabold tracking-[0.04em] {{ $badgeTone($document) }}">{{ $document->extension() }}</span>
                                <span class="flex min-w-0 flex-col gap-0.5">
                                    <span class="truncate text-sm font-semibold">{{ $document->original_name }}</span>
                                    <span class="truncate text-xs text-muted">{{ $document->linkedLabel() }}</span>
                                </span>
                            </span>
                            <span class="text-[13px]">{{ $document->folder->name }}</span>
                            <span class="text-[13px] tabular-nums">{{ $document->sizeLabel() }}</span>
                            <span class="text-[13px]">{{ $document->created_at->format('M j, Y') }}</span>
                        </button>
                    @endforeach
                    @if ($this->files->isEmpty())
                        <div class="flex flex-col items-center gap-2.5 px-5 py-10 text-center">
                            <x-icon name="image" :size="40" class="text-[#B9B6A8]" />
                            <p class="text-base font-semibold">{{ $currentFolder ? $currentFolder->name.' is empty' : 'No files yet' }}</p>
                            <p class="max-w-[380px] text-sm text-muted">Press Upload or drop files into the upload window. Each file can be linked to a member or an activity.</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- ───────────── Desktop file details ───────────── --}}
        @if ($selected)
            <aside aria-label="File details" wire:key="details-{{ $selected->id }}" class="hidden min-w-0 overflow-hidden rounded-2xl border border-line bg-white lg:block lg:flex-[1_1_320px]">
                @include('partials.document-details', ['document' => $selected, 'badgeTone' => $badgeTone])
            </aside>
        @endif
    </div>

    {{-- ───────────── Phone: take photo / upload bar ───────────── --}}
    @can('create', App\Models\Document::class)
        <div class="sticky bottom-[calc(59px+max(8px,env(safe-area-inset-bottom)))] z-20 -mx-4 grid grid-cols-2 gap-2 border-t border-line bg-white px-4 py-2.5 lg:hidden">
            <label for="document-camera" role="button" tabindex="0" x-on:keydown.enter.prevent="$el.click()" x-on:keydown.space.prevent="$el.click()" class="flex min-h-[50px] cursor-pointer items-center justify-center gap-2 rounded-xl border border-line-input bg-white text-sm font-semibold text-ink focus-visible:outline-2 focus-visible:outline-blue">
                <x-icon name="camera" :size="20" />Take photo
            </label>
            <label for="document-file" role="button" tabindex="0" x-on:keydown.enter.prevent="$el.click()" x-on:keydown.space.prevent="$el.click()" class="flex min-h-[50px] cursor-pointer items-center justify-center gap-2 rounded-xl bg-navy text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">
                <x-icon name="upload" :size="20" />Upload file
            </label>
        </div>
    @endcan

    {{-- ───────────── Phone: file details sheet ───────────── --}}
    @if ($sheetOpen && $selected)
        <dialog wire:ignore.self x-data x-init="$el.showModal()" x-on:close="$wire.closeSheet()" aria-label="File details" class="m-auto w-[min(32rem,calc(100%-2rem))] overflow-hidden rounded-2xl border border-line bg-white p-0 text-ink shadow-xl backdrop:bg-ink/50 lg:hidden">
            <div class="flex justify-end p-1">
                <button type="button" x-on:click="$el.closest('dialog').close()" class="flex size-11 items-center justify-center rounded-lg text-muted hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue" aria-label="Close">
                    <x-icon name="close" :size="20" />
                </button>
            </div>
            @include('partials.document-details', ['document' => $selected, 'badgeTone' => $badgeTone])
        </dialog>
    @endif

    {{-- ───────────── Edit details dialog ───────────── --}}
    @if ($editOpen && $selected)
        <dialog wire:ignore.self x-data x-init="$el.showModal()" x-on:close="$wire.closeEdit()" aria-labelledby="edit-file-heading" class="m-auto w-[min(32rem,calc(100%-2rem))] rounded-2xl border border-line bg-white p-0 text-ink shadow-xl backdrop:bg-ink/50">
            <form wire:submit="saveEdit" class="flex flex-col gap-4 p-5 lg:p-6" novalidate>
                <h2 id="edit-file-heading" class="font-display text-[22px] font-extrabold">Edit file details</h2>
                <div class="flex flex-col gap-1.5">
                    <label for="edit-name" class="text-sm font-semibold">Name</label>
                    <input id="edit-name" type="text" wire:model="editName" @error('editName') aria-invalid="true" @enderror class="{{ $input }}">
                    @error('editName') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="edit-folder" class="text-sm font-semibold">Folder</label>
                    <select id="edit-folder" wire:model="editFolderId" class="{{ $input }}">
                        @foreach ($this->folders as $folder)
                            <option value="{{ $folder->id }}">{{ $folder->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="edit-member" class="text-sm font-semibold">Member</label>
                        <select id="edit-member" wire:model="editMemberId" class="{{ $input }}">
                            <option value="">Not linked</option>
                            @foreach ($this->members as $member)
                                <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="edit-activity" class="text-sm font-semibold">Activity</label>
                        <select id="edit-activity" wire:model="editActivityId" class="{{ $input }}">
                            <option value="">Not linked</option>
                            @foreach ($this->activities as $activity)
                                <option value="{{ $activity->id }}">{{ $activity->display_name }} · {{ $activity->date_label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="edit-note" class="text-sm font-semibold">Note</label>
                    <textarea id="edit-note" rows="2" wire:model="editNote" class="{{ $input }} py-2"></textarea>
                </div>
                <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="min-h-11 rounded-[10px] border border-line-input bg-white px-5 text-sm font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue">Cancel</button>
                    <button type="submit" class="min-h-11 rounded-[10px] bg-blue px-6 text-sm font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">Save</button>
                </div>
            </form>
        </dialog>
    @endif

    {{-- ───────────── New folder dialog ───────────── --}}
    @if ($folderFormOpen)
        <dialog wire:ignore.self x-data x-init="$el.showModal()" x-on:close="$wire.closeFolderForm()" aria-labelledby="folder-heading" class="m-auto w-[min(26rem,calc(100%-2rem))] rounded-2xl border border-line bg-white p-0 text-ink shadow-xl backdrop:bg-ink/50">
            <form wire:submit="createFolder" class="flex flex-col gap-4 p-5 lg:p-6" novalidate>
                <h2 id="folder-heading" class="font-display text-[22px] font-extrabold">New folder</h2>
                <div class="flex flex-col gap-1.5">
                    <label for="folder-name" class="text-sm font-semibold">Folder name</label>
                    <input id="folder-name" type="text" wire:model="newFolderName" autofocus @error('newFolderName') aria-invalid="true" @enderror class="{{ $input }}">
                    @error('newFolderName') <p class="text-sm text-absent">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="$el.closest('dialog').close()" class="min-h-11 rounded-[10px] border border-line-input bg-white px-5 text-sm font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue">Cancel</button>
                    <button type="submit" class="min-h-11 rounded-[10px] bg-blue px-6 text-sm font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">Create folder</button>
                </div>
            </form>
        </dialog>
    @endif

    <livewire:document-upload :default-folder-id="$folderId" />
</div>
