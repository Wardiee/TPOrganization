<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An uploaded file on the private disk. Served only through an authorized route.
 *
 * @property int $id
 * @property int $folder_id
 * @property int|null $member_id
 * @property int|null $activity_id
 * @property string $original_name
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property string|null $note
 */
#[Fillable(['folder_id', 'member_id', 'activity_id', 'original_name', 'path', 'mime', 'size', 'note'])]
#[Hidden(['path'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /** Images the browser can show inline; everything else is downloaded. */
    public const PREVIEWABLE_IMAGES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** Allowed uploads: images, PDF, Word and Excel. */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];

    /** Largest upload, in kilobytes (20 MB). */
    public const MAX_KILOBYTES = 20480;

    /**
     * "XLSX", "PNG", "PDF": the file type badge.
     */
    public function extension(): string
    {
        return Str::upper(pathinfo($this->original_name, PATHINFO_EXTENSION)) ?: 'FILE';
    }

    /**
     * "363 KB", "2.4 MB".
     */
    public function sizeLabel(): string
    {
        return self::formatBytes($this->size);
    }

    public static function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 * 1024 => rtrim(rtrim(number_format($bytes / 1024 / 1024, 1), '0'), '.').' MB',
            $bytes >= 1024 => round($bytes / 1024).' KB',
            default => $bytes.' B',
        };
    }

    public function isPreviewableImage(): bool
    {
        return in_array($this->mime, self::PREVIEWABLE_IMAGES, true);
    }

    /**
     * Safe to show inside the browser (images and PDFs); other types always download.
     */
    public function canOpenInline(): bool
    {
        return $this->isPreviewableImage() || $this->mime === 'application/pdf';
    }

    /**
     * "Jeffrey Oliver Gumobao", "Year-End Bonding", both, or "Not linked".
     */
    public function linkedLabel(): string
    {
        return collect([$this->member?->full_name, $this->activity?->display_name])->filter()->implode(' · ') ?: 'Not linked';
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
