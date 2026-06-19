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
 * Runtime contract test for the Graduation/DocumentReview Inertia page
 * (CONTRACT §12). The reviewer queue is a Laravel paginator of students parked
 * at AnnexIiiPending, each with their document rows. This locks the exact
 * snake_case paginator shape so the controller and the React page never drift.
 * Runs on PostgreSQL 18 via RefreshDatabase.
 */

/** A step-5 student with two uploaded documents awaiting review. */
function studentInDocumentReview(): Student
{
    $graduationType = GraduationType::factory()->create();
    $required = RequiredDocument::factory()->count(2)->create();
    $graduationType->requiredDocuments()->attach($required->pluck('id'));

    $student = Student::factory()
        ->for(User::factory()->student())
        ->for($graduationType, 'graduationType')
        ->create(['status' => GraduationStatus::AnnexIiiPending->value]);

    $required->each(
        fn (RequiredDocument $doc) => StudentDocument::factory()
            ->for($student)
            ->for($doc, 'requiredDocument')
            ->uploaded()
            ->create(),
    );

    return $student;
}

it('renders the document review queue with the paginator prop shape the page consumes', function (): void {
    $admin = User::factory()->admin()->create();
    $student = studentInDocumentReview();

    actingAs($admin)
        ->get(route('admin.graduation.documents.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Graduation/DocumentReview')
                ->has('students.data', 1)
                ->has(
                    'students.data.0',
                    fn (AssertableInertia $row): AssertableInertia => $row
                        ->where('id', $student->id)
                        ->where('control_number', $student->control_number)
                        ->where('program_name', $student->program->name)
                        ->where('graduation_type_name', $student->graduationType->name)
                        ->where('full_name', "{$student->first_name} {$student->last_name}")
                        ->has('documents', 2)
                        ->has(
                            'documents.0',
                            fn (AssertableInertia $doc): AssertableInertia => $doc
                                ->has('id')
                                ->has('name')
                                ->has('status')
                                ->has('original_filename')
                                ->has('rejection_reason')
                                ->has('download_url')
                        )
                )
        );
});

it('serialises nested document statuses as enum values, never enum objects', function (): void {
    $admin = User::factory()->admin()->create();
    studentInDocumentReview();

    actingAs($admin)
        ->get(route('admin.graduation.documents.index'))
        ->assertInertia(function (AssertableInertia $page): void {
            /** @var array<int, array{documents: array<int, array{status: string}>}> $rows */
            $rows = $page->toArray()['props']['students']['data'];
            $statuses = array_column($rows[0]['documents'], 'status');

            expect($statuses)->each->toBe(DocumentStatus::Uploaded->value);
        });
});

it('only lists students parked at AnnexIiiPending', function (): void {
    $admin = User::factory()->admin()->create();
    studentInDocumentReview();

    // A student at a different stage must not appear in the review queue.
    Student::factory()->for(User::factory()->student())->create([
        'status' => GraduationStatus::PaymentPending->value,
    ]);

    actingAs($admin)
        ->get(route('admin.graduation.documents.index'))
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Graduation/DocumentReview')
                ->has('students.data', 1)
        );
});

it('forbids a non-staff user from viewing the review queue', function (): void {
    $student = User::factory()->student()->create();

    actingAs($student)
        ->get(route('admin.graduation.documents.index'))
        ->assertForbidden();
});
