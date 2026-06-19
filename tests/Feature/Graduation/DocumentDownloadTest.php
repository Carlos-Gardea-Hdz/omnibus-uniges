<?php

declare(strict_types=1);

use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\Models\StudentDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Feature coverage for the signed-temporary download (CONTRACT §10, SPEC §10.4).
 * Stored uploads live on the private `local` disk and are reachable ONLY through
 * a `signed` route that also enforces ownership (the student who owns the row, or
 * any staff member). Storage is faked. Runs on PostgreSQL 18 via RefreshDatabase.
 */

/** An uploaded document on the fake disk, owned by a freshly built student. */
function uploadedDocument(): StudentDocument
{
    $path = 'documents/1/uploads/sample.pdf';
    Storage::disk('local')->put($path, 'fake-pdf-bytes');

    $student = Student::factory()->for(User::factory()->student())->create();

    return StudentDocument::factory()
        ->for($student)
        ->uploaded()
        ->create([
            'file_path' => $path,
            'original_filename' => 'annex.pdf',
        ]);
}

/** A valid signed download URL for the given document. */
function signedDownloadUrl(StudentDocument $document): string
{
    return URL::temporarySignedRoute(
        'student.documents.download',
        now()->addHours(24),
        ['document' => $document],
    );
}

beforeEach(function (): void {
    Storage::fake('local');
});

it('lets the owning student download via a valid signed URL', function (): void {
    $document = uploadedDocument();

    actingAs($document->student->user)
        ->get(signedDownloadUrl($document))
        ->assertOk();
});

it('rejects an unsigned request with a 403', function (): void {
    $document = uploadedDocument();

    actingAs($document->student->user)
        ->get(route('student.documents.download', $document))
        ->assertForbidden();
});

it('rejects an expired signed URL with a 403', function (): void {
    $document = uploadedDocument();

    $expired = URL::temporarySignedRoute(
        'student.documents.download',
        now()->subMinute(),
        ['document' => $document],
    );

    actingAs($document->student->user)
        ->get($expired)
        ->assertForbidden();
});

it('forbids a different student from downloading even with a valid signature', function (): void {
    $document = uploadedDocument();
    $stranger = User::factory()->student()->create();

    actingAs($stranger)
        ->get(signedDownloadUrl($document))
        ->assertForbidden();
});

it('lets a staff member download any document via a valid signed URL', function (): void {
    $document = uploadedDocument();
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get(signedDownloadUrl($document))
        ->assertOk();
});
