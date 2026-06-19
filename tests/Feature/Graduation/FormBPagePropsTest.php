<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\StudyPlan;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Contract guard for the Student/FormB Inertia page (SPEC §7).
 *
 * Inertia props are untyped at runtime, so a controller that serializes a
 * different shape than the React page consumes sails past PHPStan + tsc and
 * only crashes in the browser. This test pins the EXACT prop contract — nested
 * `catalogs.*` and a `student` blob, both snake_case — so the two sides can
 * never silently drift apart again. Runs on PostgreSQL 18 via RefreshDatabase.
 */

/** Seed one row per catalog so every select field has data to render. */
function seedFormBCatalogs(): Program
{
    $program = Program::factory()->create();
    StudyPlan::factory()->for($program)->create();
    GraduationType::factory()->create();
    Professor::factory()->create();

    return $program;
}

it('renders the Student/FormB page with the catalogs and student props the React page consumes', function (): void {
    seedFormBCatalogs();

    $user = User::factory()->student()->create();
    Student::factory()->for($user)->create([
        'status' => GraduationStatus::FormBPending->value,
    ]);

    actingAs($user)
        ->get(route('student.form-b.create'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Student/FormB')
                ->has('student')
                ->has('catalogs.programs')
                ->has('catalogs.graduation_types')
                ->has('catalogs.study_plans')
                ->has('catalogs.professors')
        );
});

it('shapes each catalog entry exactly as the select fields expect', function (): void {
    seedFormBCatalogs();

    $user = User::factory()->student()->create();
    Student::factory()->for($user)->create([
        'status' => GraduationStatus::FormBPending->value,
    ]);

    actingAs($user)
        ->get(route('student.form-b.create'))
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Student/FormB')
                ->has(
                    'catalogs.programs.0',
                    fn (AssertableInertia $option): AssertableInertia => $option
                        ->has('id')
                        ->has('name')
                )
                ->has(
                    'catalogs.graduation_types.0',
                    fn (AssertableInertia $option): AssertableInertia => $option
                        ->has('id')
                        ->has('name')
                        ->has('requires_advisor')
                )
                ->has(
                    'catalogs.study_plans.0',
                    fn (AssertableInertia $option): AssertableInertia => $option
                        ->has('id')
                        ->has('name')
                        ->has('program_id')
                )
                // Professors are mapped to {id, name} — the page reads `name`,
                // not the raw first_name/last_name columns.
                ->has(
                    'catalogs.professors.0',
                    fn (AssertableInertia $option): AssertableInertia => $option
                        ->has('id')
                        ->has('name')
                        ->missing('first_name')
                        ->missing('last_name')
                )
        );
});

it('exposes the student status and rejection observations the page reads', function (): void {
    seedFormBCatalogs();

    $user = User::factory()->student()->create();
    Student::factory()->for($user)->formBRejected()->create([
        'form_b_observations' => 'Falta la firma del asesor.',
    ]);

    actingAs($user)
        ->get(route('student.form-b.create'))
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Student/FormB')
                ->where('student.status', GraduationStatus::FormBRejected->value)
                ->where('student.form_b_observations', 'Falta la firma del asesor.')
        );
});

it('passes a null student when the acting user has no Student record yet', function (): void {
    seedFormBCatalogs();

    $user = User::factory()->student()->create();

    actingAs($user)
        ->get(route('student.form-b.create'))
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Student/FormB')
                ->where('student', null)
                ->has('catalogs.programs')
        );
});
