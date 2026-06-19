<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Graduation\Enums\DocumentStatus;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Feature coverage for the 4→5 stage entry (CONTRACT §7, BeginDocumentStageAction).
 * Entering the Annex III stage seeds one pending StudentDocument per required
 * document and advances AnnexesPending → AnnexIiiPending, dispatching
 * StudentStatusChanged. Runs against PostgreSQL 18 via RefreshDatabase.
 */

/**
 * A student parked at AnnexesPending (step 4) whose graduation type requires the
 * given number of documents.
 */
function studentAtAnnexesPending(int $requiredDocs = 2): Student
{
    $graduationType = GraduationType::factory()->create();
    $required = RequiredDocument::factory()->count($requiredDocs)->create();
    $graduationType->requiredDocuments()->attach($required->pluck('id'));

    return Student::factory()
        ->for(User::factory()->student())
        ->for($graduationType, 'graduationType')
        ->create(['status' => GraduationStatus::AnnexesPending->value]);
}

it('advances 4→5, seeds a pending document per required doc, and dispatches the event', function (): void {
    Event::fake([StudentStatusChanged::class]);

    $student = studentAtAnnexesPending(requiredDocs: 3);
    $user = $student->user;

    actingAs($user)
        ->post(route('student.documents.begin'))
        ->assertRedirect();

    $student->refresh();

    expect($student->status)->toBe(GraduationStatus::AnnexIiiPending)
        ->and($student->documents()->count())->toBe(3)
        ->and($student->documents()->pluck('status')->all())
        ->each->toBe(DocumentStatus::Pending);

    Event::assertDispatched(
        StudentStatusChanged::class,
        fn (StudentStatusChanged $event): bool => $event->student->is($student)
            && $event->from === GraduationStatus::AnnexesPending
            && $event->to === GraduationStatus::AnnexIiiPending,
    );
});

it('gives every seeded document a unique file token', function (): void {
    $student = studentAtAnnexesPending(requiredDocs: 3);

    actingAs($student->user)
        ->post(route('student.documents.begin'))
        ->assertRedirect();

    $tokens = $student->documents()->pluck('file_token');

    expect($tokens)->toHaveCount(3)
        ->and($tokens->unique())->toHaveCount(3);
});

it('is idempotent: beginning twice does not duplicate rows, re-transition, or re-emit', function (): void {
    Event::fake([StudentStatusChanged::class]);

    $student = studentAtAnnexesPending(requiredDocs: 2);
    $user = $student->user;

    // First begin: 4 → 5, seeds 2 rows, emits once.
    actingAs($user)->post(route('student.documents.begin'))->assertRedirect();
    // Second begin while already at step 5: a no-op (no 500, no extra rows/event).
    actingAs($user)->post(route('student.documents.begin'))->assertRedirect();

    $student->refresh();

    expect($student->status)->toBe(GraduationStatus::AnnexIiiPending)
        ->and($student->documents()->count())->toBe(2);

    Event::assertDispatchedTimes(StudentStatusChanged::class, 1);
});

it('forbids a non-student role from beginning the document stage', function (): void {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->post(route('student.documents.begin'))
        ->assertForbidden();
});
