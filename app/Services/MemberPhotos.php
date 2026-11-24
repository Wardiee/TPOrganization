<?php

namespace App\Services;

use App\Models\Member;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Profile photos: cropped square, resized and re-saved as JPEG on the private disk.
 *
 * Re-encoding drops everything but the pixels, including the GPS location phones write into
 * photos, which matters because many members are minors.
 */
class MemberPhotos
{
    public const DISK = 'local';

    public const SIZE = 400;

    /** Bigger than any phone camera; stops a huge image from exhausting memory. */
    private const MAX_PIXELS = 50_000_000;

    /**
     * Replace the member's photo. The old file is removed only after the new one is saved.
     *
     * @throws RuntimeException when the file isn't an image GD can read
     */
    public function store(Member $member, UploadedFile $file): void
    {
        $jpeg = $this->squareJpeg((string) $file->getRealPath());

        $path = "member-photos/{$member->id}-".Str::random(16).'.jpg';
        Storage::disk(self::DISK)->put($path, $jpeg);

        $oldPath = $member->photo_path;
        $member->forceFill(['photo_path' => $path])->save();

        if ($oldPath !== null) {
            Storage::disk(self::DISK)->delete($oldPath);
        }
    }

    public function remove(Member $member): void
    {
        $oldPath = $member->photo_path;

        if ($oldPath === null) {
            return;
        }

        $member->forceFill(['photo_path' => null])->save();
        Storage::disk(self::DISK)->delete($oldPath);
    }

    /**
     * Upright, centre-cropped, SIZE×SIZE JPEG bytes.
     */
    private function squareJpeg(string $sourcePath): string
    {
        $info = @getimagesize($sourcePath);

        if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new RuntimeException('Unreadable image.');
        }

        $source = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            IMAGETYPE_GIF => @imagecreatefromgif($sourcePath),
            default => false,
        };

        if ($source === false) {
            throw new RuntimeException('Unreadable image.');
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $source = $this->applyOrientation($source, $this->jpegOrientation($sourcePath));
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $target = min(self::SIZE, $side);

        $square = imagecreatetruecolor($target, $target);
        // White behind transparent PNGs instead of black.
        imagefill($square, 0, 0, (int) imagecolorallocate($square, 255, 255, 255));
        imagecopyresampled($square, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), $target, $target, $side, $side);

        ob_start();
        imagejpeg($square, null, 85);

        return (string) ob_get_clean();
    }

    /**
     * Rotate/flip the pixels the way the camera's EXIF orientation tag says to show them.
     */
    private function applyOrientation(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [5, 6], true)) {
            $image = imagerotate($image, -90, 0) ?: $image;
        } elseif (in_array($orientation, [7, 8], true)) {
            $image = imagerotate($image, 90, 0) ?: $image;
        } elseif ($orientation === 3) {
            $image = imagerotate($image, 180, 0) ?: $image;
        }

        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        return $image;
    }

    /**
     * The EXIF orientation (1–8) of a JPEG, or 1 when there is none. Read by hand so the server
     * doesn't need PHP's exif extension.
     */
    public function jpegOrientation(string $path): int
    {
        $bytes = (string) file_get_contents($path, length: 128 * 1024);

        if (! str_starts_with($bytes, "\xFF\xD8")) {
            return 1;
        }

        $offset = 2;

        while (($bytes[$offset] ?? null) === "\xFF") {
            $marker = ord($bytes[$offset + 1] ?? "\0");
            $segmentLength = $this->readNumber('n', $bytes, $offset + 2);

            if ($segmentLength === null || $marker === 0xDA) {
                break;
            }

            if ($marker === 0xE1 && substr($bytes, $offset + 4, 6) === "Exif\0\0") {
                return $this->orientationFromTiff(substr($bytes, $offset + 10, max(0, $segmentLength - 8)));
            }

            $offset += 2 + $segmentLength;
        }

        return 1;
    }

    private function orientationFromTiff(string $tiff): int
    {
        $littleEndian = substr($tiff, 0, 2) === 'II';
        $short = $littleEndian ? 'v' : 'n';

        $ifd = $this->readNumber($littleEndian ? 'V' : 'N', $tiff, 4);
        $entries = $ifd === null ? null : $this->readNumber($short, $tiff, $ifd);

        for ($i = 0; $i < (int) $entries; $i++) {
            $entry = $ifd + 2 + $i * 12;
            $tag = $this->readNumber($short, $tiff, $entry);

            if ($tag === null) {
                break;
            }

            if ($tag === 0x0112) {
                $value = $this->readNumber($short, $tiff, $entry + 8);

                return $value !== null && $value >= 1 && $value <= 8 ? $value : 1;
            }
        }

        return 1;
    }

    /**
     * One unsigned 16-bit (n/v) or 32-bit (N/V) number, or null when the data is too short.
     */
    private function readNumber(string $format, string $bytes, int $offset): ?int
    {
        $width = in_array($format, ['n', 'v'], true) ? 2 : 4;

        if ($offset < 0 || $offset + $width > strlen($bytes)) {
            return null;
        }

        $values = unpack($format, $bytes, $offset);

        return $values === false ? null : $values[1];
    }
}
