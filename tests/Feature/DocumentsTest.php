<?php

use App\Models\Activity;
use App\Models\Document;
use App\Models\Folder;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
});

/**
 * A stored document with a real (fake-disk) file behind it.
 */
function storedDocument(string $name = 'Org chart.png', string $mime = 'image/png', array $attributes = []): Document
{
    $path = 'documents/2026/11/'.Str::random(10);
    Storage::disk('local')->put($path, 'file-bytes');

    return Document::factory()->create([...$attributes, 'original_name' => $name, 'mime' => $mime, 'path' => $path, 'size' => 10]);
}

test('files are uploaded to the private disk with their folder and links', function () {
    Storage::fake('public');
    $folder = Folder::factory()->create(['name' => 'Activity Photos']);
    $member = Member::factory()->create();
    $activity = Activity::factory()->create();

    Livewire::test('document-upload')
        ->set('uploads', [UploadedFile::fake()->image('year-end.jpg'), UploadedFile::fake()->create('waiver.pdf', 300, 'application/pdf')])
        ->assertSet('open', true)
        ->set('folderId', $folder->id)
        ->set('memberId', $member->id)
        ->set('activityId', $activity->id)
        ->set('note', 'Year-End Bonding photos')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('documents-uploaded', count: 2);

    $documents = Document::orderBy('id')->get();
    expect($documents->pluck('original_name')->all())->toBe(['year-end.jpg', 'waiver.pdf'])
        ->and($documents->every(fn ($document) => $document->folder_id === $folder->id && $document->member_id === $member->id && $document->activity_id === $activity->id))->toBeTrue()
        ->and($documents[1]->mime)->toBe('application/pdf');

    $documents->each(fn ($document) => Storage::disk('local')->assertExists($document->path));
    expect($documents[0]->path)->toStartWith('documents/')
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('only photos, PDF, Word and Excel files up to 20 MB are accepted', function (UploadedFile $file, string $errorKey) {
    $folder = Folder::factory()->create();

    Livewire::test('document-upload')
        ->set('uploads', [$file])
        ->set('folderId', $folder->id)
        ->call('save')
        ->assertHasErrors([$errorKey]);

    expect(Document::count())->toBe(0);
})->with([
    'a script' => [fn () => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'), 'uploads.0'],
    'a text file' => [fn () => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'), 'uploads.0'],
    'over 20 MB (rejected while uploading)' => [fn () => UploadedFile::fake()->create('huge.pdf', 20481, 'application/pdf'), 'uploads'],
]);

test('a failed browser upload opens the pop-up with a clear message', function () {
    Livewire::test('document-upload')
        ->call('uploadFailed')
        ->assertSet('open', true)
        ->assertSee('Files can be up to 20 MB each.');
});

test('Word and Excel files are accepted', function () {
    $folder = Folder::factory()->create();

    Livewire::test('document-upload')
        ->set('uploads', [
            UploadedFile::fake()->create('members.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            UploadedFile::fake()->create('letter.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->set('folderId', $folder->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Document::count())->toBe(2);
});

test('the profile upload links files to that member by default', function () {
    Folder::factory()->create(['name' => 'Member Documents']);
    $member = Member::factory()->create();

    Livewire::test('document-upload', ['defaultMemberId' => $member->id])
        ->set('uploads', [UploadedFile::fake()->image('id.png')])
        ->assertSet('memberId', $member->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Document::sole()->member_id)->toBe($member->id)
        ->and(Document::sole()->folder->name)->toBe('Member Documents');
});

test('a signed-in user can download a file with its original name', function () {
    $document = storedDocument('TROPANGPOTCHI_INFO 2026.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $response = $this->get(route('documents.download', $document))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('TROPANGPOTCHI_INFO 2026.xlsx')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('guests cannot download or preview files', function (string $route) {
    $document = storedDocument();
    auth()->logout();

    $this->get(route($route, $document))->assertRedirect(route('login'));
})->with(['documents.download', 'documents.preview']);

test('previews show images and PDFs inline, and force everything else to download', function (string $name, string $mime, string $disposition) {
    $document = storedDocument($name, $mime);

    $response = $this->get(route('documents.preview', $document))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith($disposition);
})->with([
    'photo' => ['photo.jpg', 'image/jpeg', 'inline'],
    'pdf' => ['form.pdf', 'application/pdf', 'inline'],
    'word' => ['letter.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'attachment'],
]);

test('a missing file gives "not found" instead of an error', function () {
    $document = storedDocument();
    Storage::disk('local')->delete($document->path);

    $this->get(route('documents.download', $document))->assertNotFound();
});

test('folder tiles count the files in each folder', function () {
    $photos = Folder::factory()->create(['name' => 'Activity Photos']);
    $records = Folder::factory()->create(['name' => 'Member Records']);
    Folder::factory()->create(['name' => 'Org Charts']);
    storedDocument('a.png', 'image/png', ['folder_id' => $photos->id]);
    storedDocument('b.png', 'image/png', ['folder_id' => $photos->id]);
    storedDocument('c.xlsx', 'application/vnd.ms-excel', ['folder_id' => $records->id]);

    $page = Livewire::test('pages::documents');
    $counts = $page->instance()->folders->mapWithKeys(fn ($folder) => [$folder->name => $page->instance()->countLabel($folder->documents_count)])->all();

    expect($counts)->toBe(['Activity Photos' => '2 files', 'Member Records' => '1 file', 'Org Charts' => 'Empty'])
        ->and($page->instance()->totals['count'])->toBe(3);

    $page->call('showFolder', $photos->id);
    expect($page->instance()->files->pluck('original_name')->sort()->values()->all())->toBe(['a.png', 'b.png']);
});

test('a file can be renamed, moved and linked, keeping its extension', function () {
    $document = storedDocument('IMG_0042.jpg', 'image/jpeg');
    $folder = Folder::factory()->create(['name' => 'Activity Photos']);
    $member = Member::factory()->create();

    Livewire::test('pages::documents')
        ->call('select', $document->id)
        ->call('openEdit')
        ->set('editName', 'Year-End group photo')
        ->set('editFolderId', $folder->id)
        ->set('editMemberId', $member->id)
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($document->fresh())
        ->original_name->toBe('Year-End group photo.jpg')
        ->folder_id->toBe($folder->id)
        ->member_id->toBe($member->id);
});

test('deleting a file removes the record and the stored file', function () {
    $document = storedDocument();

    Livewire::test('pages::documents')->call('select', $document->id)->call('deleteSelected');

    expect(Document::count())->toBe(0);
    Storage::disk('local')->assertMissing($document->path);
});

test('a new folder can be created, with a unique name', function () {
    Folder::factory()->create(['name' => 'Org Charts']);

    Livewire::test('pages::documents')
        ->call('openFolderForm')
        ->set('newFolderName', 'Org Charts')
        ->call('createFolder')
        ->assertHasErrors(['newFolderName' => 'unique'])
        ->set('newFolderName', 'Certificates')
        ->call('createFolder')
        ->assertHasNoErrors();

    expect(Folder::pluck('name')->sort()->values()->all())->toBe(['Certificates', 'Org Charts']);
});

test('the member profile lists that member\'s files with download links', function () {
    $member = Member::factory()->create();
    $document = storedDocument('Waiver.pdf', 'application/pdf', ['member_id' => $member->id]);

    $this->get(route('members.show', $member))
        ->assertOk()
        ->assertSee('Waiver.pdf')
        ->assertSee(route('documents.download', $document), false);
});
