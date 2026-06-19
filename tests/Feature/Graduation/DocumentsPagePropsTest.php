<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Graduation\Enums\DocumentStatus;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\Models\StudentDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Runtime contract test for the Student/Documents Inertia page (CONTRACT §12).
 *
 * Inertia props are untyped at runtime, so a controller that serialises a
 * different shape than the React page consumes sails past tsc + PHPStan and only
 * crashes in the browser. This pins the EXACT snake_case prop shape — top-level
 * `student_id`/`status` and a per-row `documents[]` blob — so the two sides can
 * never silently drift. Runs on PostgreSQL 18 via RefreshDatabase.
 */

/** A step-5 student with one uploaded and one pending required document. */
function studentOnDocumentsPage(): Student
{
    $graduationType = GraduationType::factory()->create();
    $uploaded = RequiredDocument::factory()->create(['name' => 'Acta de nacimiento']);
    $pending = RequiredDocument::factory()->create(['name' => 'CURP']);
    $graduationType->requiredDocuments()->attach([$uploaded->id, $pending->id]);

    $student = Student::factory()
        ->for(User::factory()->student())
        ->for($graduationType, 'graduationType')
        ->create(['status' => GraduationStatus::AnnexIiiPending->value]);

    StudentDocument::factory()->for($student)->for($uploaded, 'requiredDocument')->uploaded()->create();
    StudentDocument::factory()->for($student)->for($pending, 'requiredDocument')->pending()->create();

    return $student;
}

it('renders Student/Documents with the snake_case prop contract the page consumes', function (): void {
    $student = studentOnDocumentsPage();

    actingAs($student->user)
        ->get(route('student.documents.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Student/Documents')
                ->where('student_id', $student->id)
                ->where('status', GraduationStatus::AnnexIiiPending->value)
                ->has('documents', 2)
                ->hasAll(['student_id', 'status', 'documents'])
        );
});

it('shapes each document row exactly as the page reads it', function (): void {
    $student = studentOnDocumentsPage();

    actingAs($student->user)
        ->get(route('student.documents.index'))
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Student/Documents')
                ->has(
                    'documents.0',
                    fn (AssertableInertia $row): AssertableInertia => $row
                        ->has('id')
                        ->has('required_document_id')
                        ->has('name')
                        ->has('description')
                        ->has('status')
                        ->has('original_filename')
                        ->has('rejection_reason')
                        ->has('uploaded_at')
                        ->has('download_url')
                        ->has('allowed_mimes')
                        ->has('max_size_kb')
                )
        );
});

it('serialises the document status as the enum value, never the enum object', function (): void {
    $student = studentOnDocumentsPage();

    actingAs($student->user)
        ->get(route('student.documents.index'))
        ->assertInertia(function (AssertableInertia $page): void {
            /** @var array<int, array<string, mixed>> $documents */
            $documents = $page->toArray()['props']['documents'];

            $statuses = array_column($documents, 'status');

            expect($statuses)->toContain(DocumentStatus::Uploaded->value)
                ->and($statuses)->toContain(DocumentStatus::Pending->value)
                ->and($statuses)->each->toBeString();
        });
});

it('exposes a download_url for uploaded rows and null for pending rows', function (): void {
    $student = studentOnDocumentsPage();

    actingAs($student->user)
        ->get(route('student.documents.index'))
        ->assertInertia(function (AssertableInertia $page): void {
            /** @var array<int, array{status: string, download_url: ?string}> $documents */
            $documents = $page->toArray()['props']['documents'];

            foreach ($documents as $row) {
                if ($row['status'] === DocumentStatus::Pending->value) {
                    expect($row['download_url'])->toBeNull();
                } else {
                    expect($row['download_url'])->toBeString();
                }
            }
        });
});

it('aborts with 404 when the acting user has no Student record', function (): void {
    $user = User::factory()->student()->create();

    actingAs($user)
        ->get(route('student.documents.index'))
        ->assertNotFound();
});
