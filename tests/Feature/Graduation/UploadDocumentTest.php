<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Graduation\Enums\DocumentStatus;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\DocumentStatusChanged;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\Models\StudentDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Feature coverage for the student upload step (CONTRACT §7, UploadDocumentAction).
 * Uploading stores the file on the private `local` disk, flips the row to
 * Uploaded and dispatches DocumentStatusChanged(false) — WITHOUT advancing the
 * 9-state machine (it stays at AnnexIiiPending). Storage is faked; no real files.
 */

/**
 * A student at step 5 with one pending document seeded for a single required doc.
 * Returns the [student, requiredDocument] pair for the caller to upload against.
 *
 * @return array{0: Student, 1: RequiredDocument}
 */
function studentReadyToUpload(): array
{
    $graduationType = GraduationType::factory()->create();
    $required = RequiredDocument::factory()->create([
        'allowed_mimes' => 'application/pdf',
        'max_size_kb' => 10240,
    ]);
    $graduationType->requiredDocuments()->attach($required->id);

    $student = Student::factory()
        ->for(User::factory()->student())
        ->for($graduationType, 'graduationType')
        ->create(['status' => GraduationStatus::AnnexIiiPending->value]);

    StudentDocument::factory()
        ->for($student)
        ->for($required, 'requiredDocument')
        ->pending()
        ->create();

    return [$student, $required];
}

it('stores the file, flips the row to uploaded and dispatches the event without a transition', function (): void {
    Storage::fake('local');
    Event::fake([DocumentStatusChanged::class, StudentStatusChanged::class]);

    [$student, $required] = studentReadyToUpload();

    actingAs($student->user)
        ->post(route('student.documents.upload'), [
            'required_document_id' => $required->id,
            'file' => UploadedFile::fake()->create('annex.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect();

    $document = $student->documents()->where('required_document_id', $required->id)->first();

    expect($document->status)->toBe(DocumentStatus::Uploaded)
        ->and($document->original_filename)->toBe('annex.pdf')
        ->and($document->uploaded_at)->not->toBeNull()
        ->and($document->file_path)->not->toBeNull()
        ->and($student->fresh()->status)->toBe(GraduationStatus::AnnexIiiPending);

    Storage::disk('local')->assertExists($document->file_path);

    Event::assertDispatched(
        DocumentStatusChanged::class,
        fn (DocumentStatusChanged $event): bool => $event->document->is($document)
            && $event->allApproved === false,
    );
    Event::assertNotDispatched(StudentStatusChanged::class);
});

it('clears prior reviewer fields when replacing a previously rejected document', function (): void {
    Storage::fake('local');

    [$student, $required] = studentReadyToUpload();
    $reviewer = User::factory()->admin()->create();

    $student->documents()->where('required_document_id', $required->id)->first()->update([
        'status' => DocumentStatus::Rejected,
        'rejection_reason' => 'Ilegible.',
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => now(),
    ]);

    actingAs($student->user)
        ->post(route('student.documents.upload'), [
            'required_document_id' => $required->id,
            'file' => UploadedFile::fake()->create('fixed.pdf', 150, 'application/pdf'),
        ])
        ->assertRedirect();

    $document = $student->documents()->where('required_document_id', $required->id)->first();

    expect($document->status)->toBe(DocumentStatus::Uploaded)
        ->and($document->rejection_reason)->toBeNull()
        ->and($document->reviewed_by)->toBeNull()
        ->and($document->reviewed_at)->toBeNull();
});

it('rejects an upload with no file as a session error (302), not a 422', function (): void {
    Storage::fake('local');

    [$student, $required] = studentReadyToUpload();

    actingAs($student->user)
        ->post(route('student.documents.upload'), [
            'required_document_id' => $required->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('file');
});

it('rejects an upload referencing a non-existent required document', function (): void {
    Storage::fake('local');

    [$student] = studentReadyToUpload();

    actingAs($student->user)
        ->post(route('student.documents.upload'), [
            'required_document_id' => 999_999,
            'file' => UploadedFile::fake()->create('annex.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('required_document_id');
});

it('rejects an upload for a required document outside the student graduation type', function (): void {
    Storage::fake('local');

    [$student] = studentReadyToUpload();

    // Exists in required_documents, but attached to a DIFFERENT graduation type.
    $foreignType = GraduationType::factory()->create();
    $foreign = RequiredDocument::factory()->create();
    $foreignType->requiredDocuments()->attach($foreign->id);

    actingAs($student->user)
        ->post(route('student.documents.upload'), [
            'required_document_id' => $foreign->id,
            'file' => UploadedFile::fake()->create('annex.pdf', 100, 'application/pdf'),
        ])
        ->assertNotFound();

    expect(StudentDocument::query()->where('required_document_id', $foreign->id)->exists())->toBeFalse();
});

it('forbids a non-student role from uploading', function (): void {
    Storage::fake('local');

    [, $required] = studentReadyToUpload();
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->post(route('student.documents.upload'), [
            'required_document_id' => $required->id,
            'file' => UploadedFile::fake()->create('annex.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});
