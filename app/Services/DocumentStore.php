<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Saves uploads to the private disk and removes them again. Files never live under public/.
 */
class DocumentStore
{
    public const DISK = 'local';

    /**
     * @param  array{folder_id: int, member_id: int|null, activity_id: int|null, note: string|null}  $details
     */
    public function store(UploadedFile $file, array $details): Document
    {
        $path = $file->store('documents/'.now()->format('Y/m'), self::DISK);

        return Document::create([
            ...$details,
            'original_name' => $this->cleanName($file->getClientOriginalName()),
            'path' => $path,
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Delete the record and the file. The file goes only after the record is gone.
     */
    public function delete(Document $document): void
    {
        $path = $document->path;

        DB::transaction(fn () => $document->delete());

        Storage::disk(self::DISK)->delete($path);
    }

    /**
     * Keep the original name for display, minus any folder parts or control characters.
     */
    public function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';

        return mb_substr(trim($name), 0, 200) ?: 'file';
    }
}
