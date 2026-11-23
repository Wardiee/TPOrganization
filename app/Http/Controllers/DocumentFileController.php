<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentStore;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way to fetch an uploaded file: signed-in, authorized, never cached by shared devices.
 */
class DocumentFileController extends Controller
{
    /**
     * Download with the original file name.
     */
    public function download(Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        return $this->stream($document, 'attachment');
    }

    /**
     * Show an image or PDF in the browser (thumbnails and previews). Other types are downloaded.
     */
    public function preview(Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        return $this->stream($document, $document->canOpenInline() ? 'inline' : 'attachment');
    }

    private function stream(Document $document, string $disposition): StreamedResponse
    {
        $disk = Storage::disk(DocumentStore::DISK);

        abort_unless($disk->exists($document->path), 404);

        return $disk->response($document->path, $document->original_name, [
            'Content-Type' => $document->canOpenInline() ? $document->mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], $disposition);
    }
}
