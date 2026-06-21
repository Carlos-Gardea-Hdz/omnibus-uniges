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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Feature coverage for the staff document review (CONTRACT §7,
 * Approve/RejectDocumentAction). Approving the LAST required document closes the
 * gate: 5→6 with documents_completed_at set and BOTH DocumentStatusChanged(true)
 * and StudentStatusChanged(5→6) dispatched. Approving a non-final doc, or any
 * rejection, never advances the machine. Runs on PostgreSQL 18 via RefreshDatabase.
 */

/**
 * A student at step 5 with `$count` uploaded documents awaiting review.
 *
 * @return array{0: Student, 1: Collection<int, StudentDocument>}
 */
function studentWithUploadedDocs(int $count): array
{
    $graduationType = GraduationType::factory()->create();
    $required = RequiredDocument::factory()->count($count)->create();
    $graduationType->requiredDocuments()->attach($required->pluck('id'));

    $student = Student::factory()
        ->for(User::factory()->student())
        ->for($graduationType, 'graduationType')
        ->create(['status' => GraduationStatus::AnnexIiiPending->value]);

    $documents = $required->map(
        fn (RequiredDocument $doc): StudentDocument => StudentDocument::factory()
            ->for($student)
            ->for($doc, 'requiredDocument')
            ->uploaded()
            ->create(),
    );

    return [$student, $documents];
}

it('approves a non-final document: row approved, reviewer recorded, no transition', function (): void {
    Event::fake([DocumentStatusChanged::class, StudentStatusChanged::class]);

    $admin = User::factory()->admin()->create();
    [$student, $documents] = studentWithUploadedDocs(2);
    $first = $documents->first();

    actingAs($admin)
        ->post(route('admin.graduation.documents.approve', $first))
        ->assertRedirect();

    $first->refresh();

    expect($first->status)->toBe(DocumentStatus::Approved)
        ->and($first->reviewed_by)->toBe($admin->id)
        ->and($first->reviewed_at)->not->toBeNull()
        ->and($student->fresh()->status)->toBe(GraduationStatus::AnnexIiiPending);

    Event::assertDispatched(
        DocumentStatusChanged::class,
        fn (DocumentStatusChanged $event): bool => $event->document->is($first)
            && $event->allApproved === false,
    );
    Event::assertNotDispatched(StudentStatusChanged::class);
});

it('approving the final required document advances 5→6 and dispatches both events', function (): void {
    Event::fake([DocumentStatusChanged::class, StudentStatusChanged::class]);

    $admin = User::factory()->admin()->create();
    [$student, $documents] = studentWithUploadedDocs(2);

    // Approve the first doc up front (no transition yet).
    $documents->first()->update([
        'status' => DocumentStatus::Approved,
        'reviewed_by' => $admin->id,
        'reviewed_at' => now(),
    ]);

    $last = $documents->last();

    actingAs($admin)
        ->post(route('admin.graduation.documents.approve', $last))
        ->assertRedirect();

    $student->refresh();

    expect($last->fresh()->status)->toBe(DocumentStatus::Approved)
        ->and($student->status)->toBe(GraduationStatus::PaymentPending)
        ->and($student->documents_completed_at)->not->toBeNull();

    Event::assertDispatched(
        DocumentStatusChanged::class,
        fn (DocumentStatusChanged $event): bool => $event->document->is($last)
            && $event->allApproved === true,
    );
    Event::assertDispatched(
        StudentStatusChanged::class,
        fn (StudentStatusChanged $event): bool => $event->student->is($student)
            && $event->from === GraduationStatus::AnnexIiiPending
            && $event->to === GraduationStatus::PaymentPending,
    );
});

it('a single unapproved document blocks the 5→6 advance', function (): void {
    Event::fake([StudentStatusChanged::class]);

    $admin = User::factory()->admin()->create();
    [$student, $documents] = studentWithUploadedDocs(3);

    // Approve only one of the three required documents.
    actingAs($admin)
        ->post(route('admin.graduation.documents.approve', $documents->first()))
        ->assertRedirect();

    expect($student->fresh()->status)->toBe(GraduationStatus::AnnexIiiPending)
        ->and($student->fresh()->documents_completed_at)->toBeNull();

    Event::assertNotDispatched(StudentStatusChanged::class);
});

it('rejects a document: row rejected, reason stored, reviewer recorded, no transition', function (): void {
    Event::fake([DocumentStatusChanged::class, StudentStatusChanged::class]);

    $admin = User::factory()->admin()->create();
    [$student, $documents] = studentWithUploadedDocs(2);
    $target = $documents->first();

    actingAs($admin)
        ->post(route('admin.graduation.documents.reject', $target), [
            'rejection_reason' => 'El documento está incompleto, falta la última página.',
        ])
        ->assertRedirect();

    $target->refresh();

    expect($target->status)->toBe(DocumentStatus::Rejected)
        ->and($target->rejection_reason)->toBe('El documento está incompleto, falta la última página.')
        ->and($target->reviewed_by)->toBe($admin->id)
        ->and($student->fresh()->status)->toBe(GraduationStatus::AnnexIiiPending);

    Event::assertDispatched(
        DocumentStatusChanged::class,
        fn (DocumentStatusChanged $event): bool => $event->document->is($target)
            && $event->allApproved === false,
    );
    Event::assertNotDispatched(StudentStatusChanged::class);
});

it('rejects a document with an empty reason as a session error (302), not a 422', function (): void {
    $admin = User::factory()->admin()->create();
    [, $documents] = studentWithUploadedDocs(1);

    actingAs($admin)
        ->post(route('admin.graduation.documents.reject', $documents->first()), [
            'rejection_reason' => '',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('rejection_reason');
});

it('rejects a too-short reason as a session error', function (): void {
    $admin = User::factory()->admin()->create();
    [, $documents] = studentWithUploadedDocs(1);

    actingAs($admin)
        ->post(route('admin.graduation.documents.reject', $documents->first()), [
            'rejection_reason' => 'no',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('rejection_reason');
});

it('forbids a non-staff user from approving a document', function (): void {
    $intruder = User::factory()->student()->create();
    [, $documents] = studentWithUploadedDocs(1);

    actingAs($intruder)
        ->post(route('admin.graduation.documents.approve', $documents->first()))
        ->assertForbidden();

    expect($documents->first()->fresh()->status)->toBe(DocumentStatus::Uploaded);
});

it('forbids a non-staff user from rejecting a document', function (): void {
    $intruder = User::factory()->student()->create();
    [, $documents] = studentWithUploadedDocs(1);

    actingAs($intruder)
        ->post(route('admin.graduation.documents.reject', $documents->first()), [
            'rejection_reason' => 'should never be applied',
        ])
        ->assertForbidden();
});

it('allows a secretary to review documents', function (): void {
    $secretary = User::factory()->secretary()->create();
    [, $documents] = studentWithUploadedDocs(1);

    actingAs($secretary)
        ->post(route('admin.graduation.documents.approve', $documents->first()))
        ->assertRedirect();

    expect($documents->first()->fresh()->status)->toBe(DocumentStatus::Approved);
});

/*
 * WARN 3 — entry-state gate. The {studentDocument} route binds the document
 * directly, so without a guard staff could approve/reject a document for a
 * student already PAST the document stage. Both Actions now assert the owning
 * student is in AnnexIiiPending; an out-of-stage review is a graceful 302 +
 * 'document' field error with no mutation.
 */

it('refuses to approve a document for a student past the document stage (302, no mutation)', function (): void {
    Event::fake([DocumentStatusChanged::class, StudentStatusChanged::class]);

    $admin = User::factory()->admin()->create();
    [$student, $documents] = studentWithUploadedDocs(2);

    // Move the student forward, out of the review stage (status is guarded —
    // set it directly, not via mass-assignment, WARN 5).
    $student->status = GraduationStatus::PaymentPending;
    $student->save();
    $target = $documents->first();

    actingAs($admin)
        ->post(route('admin.graduation.documents.approve', $target))
        ->assertRedirect()
        ->assertSessionHasErrors('document');

    // Untouched: still the uploaded state, no reviewer recorded.
    expect($target->fresh()->status)->toBe(DocumentStatus::Uploaded)
        ->and($target->fresh()->reviewed_by)->toBeNull()
        ->and($student->fresh()->status)->toBe(GraduationStatus::PaymentPending);

    Event::assertNotDispatched(DocumentStatusChanged::class);
    Event::assertNotDispatched(StudentStatusChanged::class);
});

it('refuses to reject a document for a student past the document stage (302, no mutation)', function (): void {
    Event::fake([DocumentStatusChanged::class]);

    $admin = User::factory()->admin()->create();
    [$student, $documents] = studentWithUploadedDocs(2);

    // status is guarded (WARN 5) — set it directly, not via mass-assignment.
    $student->status = GraduationStatus::PaymentPending;
    $student->save();
    $target = $documents->first();

    actingAs($admin)
        ->post(route('admin.graduation.documents.reject', $target), [
            'rejection_reason' => 'El documento está incompleto, falta la última página.',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('document');

    expect($target->fresh()->status)->toBe(DocumentStatus::Uploaded)
        ->and($target->fresh()->rejection_reason)->toBeNull()
        ->and($target->fresh()->reviewed_by)->toBeNull();

    Event::assertNotDispatched(DocumentStatusChanged::class);
});

it('approves a document for a student IN the document stage (AnnexIiiPending) — gate passes', function (): void {
    $admin = User::factory()->admin()->create();
    [, $documents] = studentWithUploadedDocs(2);

    actingAs($admin)
        ->post(route('admin.graduation.documents.approve', $documents->first()))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($documents->first()->fresh()->status)->toBe(DocumentStatus::Approved);
});
