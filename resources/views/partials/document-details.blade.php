{{-- File details: preview, facts and actions. Used by the desktop side panel and the phone sheet on Documents. --}}
<div class="flex h-[200px] items-center justify-center border-b border-line bg-[#F3F2EC]">
    @if ($document->isPreviewableImage())
        <img src="{{ route('documents.preview', $document) }}" alt="Preview of {{ $document->original_name }}" class="max-h-full max-w-full object-contain">
    @else
        <span class="flex flex-col items-center gap-2">
            <span class="flex size-21 items-center justify-center rounded-[14px] text-lg font-extrabold tracking-[0.04em] {{ $badgeTone($document) }}">{{ $document->extension() }}</span>
            <span class="text-xs text-muted">{{ $document->canOpenInline() ? 'Open to view full size' : 'Download to open' }}</span>
        </span>
    @endif
</div>
<div class="flex flex-col gap-4 px-[22px] py-5">
    <div class="flex flex-col gap-1">
        <p class="text-base font-bold break-words">{{ $document->original_name }}</p>
        @if ($document->note)
            <p class="text-[13px] text-muted">{{ $document->note }}</p>
        @endif
    </div>
    <dl class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-0.5"><dt class="text-xs text-muted">Folder</dt><dd class="text-sm font-semibold">{{ $document->folder->name }}</dd></div>
        <div class="flex flex-col gap-0.5"><dt class="text-xs text-muted">Size</dt><dd class="text-sm font-semibold">{{ $document->sizeLabel() }}</dd></div>
        <div class="flex flex-col gap-0.5"><dt class="text-xs text-muted">Added</dt><dd class="text-sm font-semibold">{{ $document->created_at->format('M j, Y') }}</dd></div>
        <div class="flex flex-col gap-0.5">
            <dt class="text-xs text-muted">Linked to</dt>
            <dd class="text-sm font-semibold">
                @if ($document->member)
                    <a href="{{ route('members.show', $document->member) }}" wire:navigate class="text-blue hover:text-navy">{{ $document->member->full_name }}</a>
                @endif
                @if ($document->activity)
                    <span class="block">{{ $document->activity->display_name }}</span>
                @endif
                @unless ($document->member || $document->activity)
                    Not linked
                @endunless
            </dd>
        </div>
    </dl>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('documents.download', $document) }}" class="inline-flex min-h-11 flex-auto items-center justify-center rounded-[10px] bg-blue px-3.5 text-sm font-semibold text-white hover:bg-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue">Download</a>
        @if ($document->canOpenInline())
            <a href="{{ route('documents.preview', $document) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-[10px] border border-line-input bg-white px-3.5 text-sm font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue">Open</a>
        @endif
        @can('update', $document)
            <button type="button" wire:click="openEdit" class="min-h-11 rounded-[10px] border border-line-input bg-white px-3.5 text-sm font-semibold text-ink hover:bg-bg focus-visible:outline-2 focus-visible:outline-blue">Rename / move</button>
        @endcan
        @can('delete', $document)
            <button type="button" wire:click="deleteSelected" wire:confirm="Delete “{{ $document->original_name }}”? The file is removed for good." class="min-h-11 rounded-[10px] border border-line-input bg-white px-3.5 text-sm font-semibold text-absent hover:bg-[#FFF4EC] focus-visible:outline-2 focus-visible:outline-blue">Delete</button>
        @endcan
    </div>
</div>
