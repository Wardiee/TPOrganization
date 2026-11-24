<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\MemberPhotos;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a member's profile photo from the private disk to signed-in, authorized users only.
 */
class MemberPhotoController extends Controller
{
    public function __invoke(Member $member): StreamedResponse
    {
        Gate::authorize('view', $member);

        $disk = Storage::disk(MemberPhotos::DISK);

        abort_if($member->photo_path === null || ! $disk->exists($member->photo_path), 404);

        return $disk->response($member->photo_path, 'photo.jpg', [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ], 'inline');
    }
}
