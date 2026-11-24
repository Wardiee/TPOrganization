<?php

use App\Models\Member;
use App\Models\User;
use App\Services\MemberPhotos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * A 200×100 JPEG: left half red, right half blue, optionally tagged with an EXIF orientation.
 */
function halfRedHalfBlueJpeg(?int $orientation = null): string
{
    $image = imagecreatetruecolor(200, 100);
    imagefilledrectangle($image, 0, 0, 99, 99, imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, 100, 0, 199, 99, imagecolorallocate($image, 0, 0, 255));
    ob_start();
    imagejpeg($image, null, 95);
    $jpeg = ob_get_clean();

    if ($orientation === null) {
        return $jpeg;
    }

    // Little-endian TIFF with one IFD entry: Orientation (0x0112), SHORT, count 1.
    $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1)
        .pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientation).pack('v', 0)
        .pack('V', 0);
    $app1 = "Exif\0\0".$tiff;

    return "\xFF\xD8\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
}

function storedPhoto(Member $member): GdImage
{
    return imagecreatefromstring(Storage::disk('local')->get($member->fresh()->photo_path));
}

function isRed(GdImage $image, int $x, int $y): bool
{
    $rgb = imagecolorat($image, $x, $y);

    return ($rgb >> 16 & 0xFF) > 200 && ($rgb & 0xFF) < 60;
}

test('a damaged orientation tag is ignored instead of breaking the upload', function () {
    $damaged = "\xFF\xD8\xFF\xE1\x00\x10Exif\0\0II*\0\xFF\xFF\xFF\xFF";
    $upload = UploadedFile::fake()->createWithContent('damaged.jpg', $damaged);

    expect(app(MemberPhotos::class)->jpegOrientation($upload->getRealPath()))->toBe(1);
});

describe('on the profile', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
    });

    test('a chosen photo is saved privately as a 400px square and shown on the profile', function () {
        $member = Member::factory()->create();

        Livewire::test('pages::members.show', ['member' => $member])
            ->set('photo', UploadedFile::fake()->image('me.png', 800, 600))
            ->assertHasNoErrors()
            ->assertSee(route('members.photo', $member), false);

        $path = $member->fresh()->photo_path;
        expect($path)->toStartWith('member-photos/')
            ->and(Storage::disk('local')->exists($path))->toBeTrue()
            ->and(getimagesizefromstring(Storage::disk('local')->get($path)))->toMatchArray([0 => 400, 1 => 400, 'mime' => 'image/jpeg']);
    });

    test('a new photo replaces the old file and removing it deletes the file', function () {
        $member = Member::factory()->create();
        $component = Livewire::test('pages::members.show', ['member' => $member])
            ->set('photo', UploadedFile::fake()->image('first.jpg', 500, 500));
        $firstPath = $member->fresh()->photo_path;

        $component->set('photo', UploadedFile::fake()->image('second.jpg', 500, 500));
        $secondPath = $member->fresh()->photo_path;

        expect($secondPath)->not->toBe($firstPath)
            ->and(Storage::disk('local')->exists($firstPath))->toBeFalse();

        $component->call('removePhoto');

        expect($member->fresh()->photo_path)->toBeNull()
            ->and(Storage::disk('local')->exists($secondPath))->toBeFalse();
    });

    test('files that are not photos are refused', function () {
        $member = Member::factory()->create();

        Livewire::test('pages::members.show', ['member' => $member])
            ->set('photo', UploadedFile::fake()->create('waiver.pdf', 100, 'application/pdf'))
            ->assertHasErrors('photo');

        expect($member->fresh()->photo_path)->toBeNull();
    });

    test('a photo that only claims to be an image is refused without saving', function () {
        $member = Member::factory()->create();

        expect(fn () => app(MemberPhotos::class)->store($member, UploadedFile::fake()->createWithContent('fake.jpg', 'not really a jpeg')))
            ->toThrow(RuntimeException::class);

        expect($member->fresh()->photo_path)->toBeNull()
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    });

    test('phone photos are turned upright and their hidden data is removed', function () {
        $member = Member::factory()->create();
        $upload = UploadedFile::fake()->createWithContent('phone.jpg', halfRedHalfBlueJpeg(orientation: 6));

        expect(app(MemberPhotos::class)->jpegOrientation($upload->getRealPath()))->toBe(6);

        app(MemberPhotos::class)->store($member, $upload);

        // Rotated a quarter turn clockwise, the red left half ends up on top. Small photos keep their size (100px).
        $photo = storedPhoto($member);
        expect(isRed($photo, 80, 20))->toBeTrue()
            ->and(isRed($photo, 80, 80))->toBeFalse()
            ->and(Storage::disk('local')->get($member->fresh()->photo_path))->not->toContain('Exif');
    });

    test('photos without an orientation tag are left as they are', function () {
        $member = Member::factory()->create();

        app(MemberPhotos::class)->store($member, UploadedFile::fake()->createWithContent('plain.jpg', halfRedHalfBlueJpeg()));

        $photo = storedPhoto($member);
        expect(isRed($photo, 20, 50))->toBeTrue()
            ->and(isRed($photo, 80, 50))->toBeFalse();
    });

    test('the members list shows the photo', function () {
        $member = Member::factory()->create();
        app(MemberPhotos::class)->store($member, UploadedFile::fake()->image('me.jpg', 300, 300));

        $this->get(route('members.index'))->assertOk()->assertSee($member->fresh()->photoUrl(), false);
    });
});

describe('the photo address', function () {
    test('serves the photo only to signed-in users and never lets it be cached', function () {
        $member = Member::factory()->create();
        app(MemberPhotos::class)->store($member, UploadedFile::fake()->image('me.jpg', 300, 300));

        $this->get(route('members.photo', $member))->assertRedirect(route('login'));

        $response = $this->actingAs(User::factory()->create())->get(route('members.photo', $member));

        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        expect($response->headers->get('Cache-Control'))->toContain('no-store');
    });

    test('is not found when the member has no photo', function () {
        $member = Member::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('members.photo', $member))->assertNotFound();
    });
});
