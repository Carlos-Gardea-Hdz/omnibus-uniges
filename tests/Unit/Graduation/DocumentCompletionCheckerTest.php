<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Graduation\Enums\DocumentStatus;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\Models\StudentDocument;
use App\Domain\Graduation\Services\DocumentCompletionChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

covers(DocumentCompletionChecker::class);

/*
 * DocumentCompletionChecker decides whether a student has cleared the Annex III
 * document gate (CONTRACT §5): true ONLY when the graduation type's required-doc
 * set is non-empty AND every required doc has an approved student_documents row.
 * It touches relations, so it runs against PostgreSQL 18 via RefreshDatabase.
 */

/**
 * A student whose graduation type requires the given number of documents, each
 * already represented by a StudentDocument row in the supplied status.
 */
function studentWithRequiredDocs(int $count, DocumentStatus $status): Student
{
    $graduationType = GraduationType::factory()->create();
    $required = RequiredDocument::factory()->count($count)->create();
    $graduationType->requiredDocuments()->attach($required->pluck('id'));

    $student = Student::factory()->for($graduationType, 'graduationType')->create();

    foreach ($required as $document) {
        StudentDocument::factory()
            ->for($student)
            ->for($document, 'requiredDocument')
            ->state(['status' => $status])
            ->create();
    }

    return $student;
}

it('returns true only when every required document is approved', function (): void {
    $student = studentWithRequiredDocs(3, DocumentStatus::Approved);

    expect((new DocumentCompletionChecker)->allRequiredApproved($student))->toBeTrue();
});

it('returns false when a single required document is still unapproved', function (): void {
    $student = studentWithRequiredDocs(3, DocumentStatus::Approved);

    // Demote one approved doc to uploaded — the gate must close again.
    $student->documents()->first()->update(['status' => DocumentStatus::Uploaded]);

    expect((new DocumentCompletionChecker)->allRequiredApproved($student->fresh()))->toBeFalse();
});

it('returns false when the required set is empty (no docs required)', function (): void {
    $graduationType = GraduationType::factory()->create();
    $student = Student::factory()->for($graduationType, 'graduationType')->create();

    expect((new DocumentCompletionChecker)->allRequiredApproved($student))->toBeFalse();
});

it('ignores extra approved documents that are not part of the required set', function (): void {
    $student = studentWithRequiredDocs(2, DocumentStatus::Approved);

    // An approved doc tied to a required document NOT attached to this graduation
    // type must not satisfy the gate for a missing required one.
    $orphanRequired = RequiredDocument::factory()->create();
    StudentDocument::factory()
        ->for($student)
        ->for($orphanRequired, 'requiredDocument')
        ->state(['status' => DocumentStatus::Approved])
        ->create();

    // Still complete: the two genuinely required docs are approved.
    expect((new DocumentCompletionChecker)->allRequiredApproved($student->fresh()))->toBeTrue();
});
