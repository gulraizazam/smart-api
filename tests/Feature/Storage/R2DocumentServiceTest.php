<?php

declare(strict_types=1);

use App\Services\Storage\R2DocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * R2DocumentService contract tests — exercised against Laravel's in-memory
 * fake disk so the suite doesn't touch Cloudflare. temporaryUrl() is
 * covered by an integration test elsewhere (the fake disk's temporaryUrl
 * stub returns a non-signed placeholder).
 */
function makeR2Service(string $diskName = 'test-r2'): R2DocumentService
{
    return new R2DocumentService(Storage::disk($diskName));
}

beforeEach(function () {
    Storage::fake('test-r2');
});

it('stores a file under the given directory with an explicit filename', function () {
    $service = makeR2Service();

    $key = $service->store(
        UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        'patients/42/documents',
        ['pdf'],
        'fixed-name.pdf',
    );

    expect($key)->toBe('patients/42/documents/fixed-name.pdf');
    Storage::disk('test-r2')->assertExists($key);
});

it('rejects files outside the allowed extension list', function () {
    $service = makeR2Service();

    $service->store(
        UploadedFile::fake()->create('dangerous.exe', 1, 'application/x-msdownload'),
        'patients/42/documents',
        ['pdf', 'png'],
    );
})->throws(
    InvalidArgumentException::class,
    'File type not allowed',
);

it('skips the extension gate when the allow-list is empty', function () {
    $service = makeR2Service();

    $key = $service->store(
        UploadedFile::fake()->create('anything.weird', 1),
        'misc',
        [],
        'kept.weird',
    );

    Storage::disk('test-r2')->assertExists($key);
});

it('exists and delete round-trip cleanly', function () {
    $service = makeR2Service();
    $key = $service->store(
        UploadedFile::fake()->create('gone.pdf', 1, 'application/pdf'),
        'patients/1/documents',
        ['pdf'],
        'gone.pdf',
    );

    expect($service->exists($key))->toBeTrue();

    $service->delete($key);

    expect($service->exists($key))->toBeFalse();
});

it('swallows delete errors so the caller is never 500 by a stray orphan', function () {
    $service = makeR2Service();

    // Non-existent key: underlying FilesystemAdapter returns false, no throw.
    // Empty key: early-return path. Either way: no exception reaches the caller.
    $service->delete('never/uploaded.pdf');
    $service->delete('');

    expect(true)->toBeTrue();
});

it('streams a stored file with the requested disposition and display name', function () {
    $service = makeR2Service();
    $key = $service->store(
        UploadedFile::fake()->create('report.pdf', 2, 'application/pdf'),
        'reports',
        ['pdf'],
        'r.pdf',
    );

    $response = $service->stream($key, 'Quarterly Report.pdf', 'attachment');

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain('Quarterly Report.pdf');
});
