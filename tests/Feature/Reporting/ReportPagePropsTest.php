<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Domain\Jury\Enums\JuryRole;
use App\Domain\Jury\Models\JuryAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Prop-contract test for the four report pages (spec 008 §2.4, scenario 14). One
 * section per report locks the component name + the EXACT snake_case prop shape:
 * status/role serialised as the enum VALUE string, dates ISO-8601 (or null),
 * rates as ints, the fixed 9-row cohort breakdown. A future controller/page
 * drift (a renamed key, a float rate, a missing field) fails CI. Boots the app +
 * PostgreSQL 18 (RefreshDatabase).
 */

// ---------------------------------------------------------------------------
// Reporting/Graduates
// ---------------------------------------------------------------------------

it('locks the Reporting/Graduates prop shape', function (): void {
    $admin = User::factory()->admin()->create();
    $program = Program::factory()->create();
    $type = GraduationType::factory()->create();

    Student::factory()->ceremonyPassed()->create([
        'program_id' => $program->id,
        'graduation_type_id' => $type->id,
        'status' => GraduationStatus::Graduated,
        'graduation_date' => '2025-06-01',
        'control_number' => '20250019',
    ]);

    actingAs($admin)
        ->get(route('admin.reports.graduates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Graduates')
                ->has('graduates')
                ->has('graduates.0', fn ($row) => $row
                    ->has('id')
                    ->has('control_number')
                    ->has('full_name')
                    ->has('program_name')
                    ->has('graduation_type_name')
                    ->has('diploma_folio')
                    ->has('record_book')
                    ->has('record_sheet')
                    ->where('graduation_date', '2025-06-01') // ISO-8601 Y-m-d
                    ->etc())
                ->has('filters', fn ($f) => $f->has('year')->has('program_id'))
                ->has('filter_options', fn ($o) => $o->has('years')->has('programs'))
                ->has('total'),
        );
});

// ---------------------------------------------------------------------------
// Reporting/TerminalEfficiency
// ---------------------------------------------------------------------------

it('locks the Reporting/TerminalEfficiency prop shape with int rates', function (): void {
    $admin = User::factory()->admin()->create();
    $program = Program::factory()->create();

    Student::factory()->ceremonyPassed()->create([
        'program_id' => $program->id,
        'status' => GraduationStatus::Graduated,
        'enrollment_date' => '2019-09-01',
        'graduation_date' => '2023-07-01',
    ]);
    Student::factory()->formBReview()->create([
        'program_id' => $program->id,
        'enrollment_date' => '2019-09-01',
    ]);

    actingAs($admin)
        ->get(route('admin.reports.terminal-efficiency'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/TerminalEfficiency')
                ->has('by_cohort.0', fn ($row) => $row
                    ->has('cohort_year')
                    ->has('total')
                    ->has('graduates')
                    ->where('rate', fn ($rate) => is_int($rate) && $rate >= 0 && $rate <= 100)
                    ->etc())
                ->has('by_program.0', fn ($row) => $row
                    ->has('program_id')
                    ->has('program_name')
                    ->has('total')
                    ->has('graduates')
                    ->where('rate', fn ($rate) => is_int($rate) && $rate >= 0 && $rate <= 100)
                    ->etc())
                ->has('overall', fn ($o) => $o
                    ->has('total')
                    ->has('graduates')
                    ->where('rate', fn ($rate) => is_int($rate) && $rate >= 0 && $rate <= 100)),
        );
});

// ---------------------------------------------------------------------------
// Reporting/Cohorts
// ---------------------------------------------------------------------------

it('locks the Reporting/Cohorts prop shape with a 9-row by_status breakdown', function (): void {
    $admin = User::factory()->admin()->create();

    Student::factory()->formBReview()->create(['enrollment_date' => '2020-09-01']);

    actingAs($admin)
        ->get(route('admin.reports.cohorts'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Cohorts')
                ->has('cohorts.0', fn ($row) => $row
                    ->has('cohort_year')
                    ->has('total')
                    ->has('graduated')
                    ->has('in_progress')
                    ->has('by_status', 9)
                    ->has('by_status.0', fn ($status) => $status
                        ->has('status')
                        ->has('step')
                        ->has('label_key')
                        ->has('color')
                        ->has('count')))
                // The first breakdown row is always the first GraduationStatus case (step 1).
                ->where('cohorts.0.by_status.0.status', GraduationStatus::cases()[0]->value)
                ->where('cohorts.0.by_status.0.step', 1),
        );
});

// ---------------------------------------------------------------------------
// Reporting/JudgeCertificates
// ---------------------------------------------------------------------------

it('locks the Reporting/JudgeCertificates prop shape with role value strings', function (): void {
    $admin = User::factory()->admin()->create();
    $program = Program::factory()->create();

    $student = Student::factory()->ceremonyScheduled()->create([
        'program_id' => $program->id,
        'status' => GraduationStatus::CeremonyScheduled,
    ]);

    JuryAssignment::factory()->create([
        'student_id' => $student->id,
        'president_professor_id' => Professor::factory()->create()->id,
        'secretary_professor_id' => Professor::factory()->create()->id,
        'vocal_professor_id' => Professor::factory()->create()->id,
        'substitute_professor_id' => Professor::factory()->create()->id,
    ]);

    actingAs($admin)
        ->get(route('admin.reports.judge-certificates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/JudgeCertificates')
                ->has('professors.0', fn ($p) => $p
                    ->has('professor_id')
                    ->has('professor_name')
                    ->has('assignment_count')
                    ->has('assignments.0', fn ($a) => $a
                        ->has('jury_assignment_id')
                        // role is a JuryRole VALUE string, not a label or enum object.
                        ->where('role', fn ($role) => in_array(
                            $role,
                            array_map(fn (JuryRole $r) => $r->value, JuryRole::cases()),
                            strict: true,
                        ))
                        ->has('role_label_key')
                        ->has('student_id')
                        ->has('student_control_number')
                        ->has('student_name')
                        ->has('program_name')
                        // student_status is a GraduationStatus VALUE string.
                        ->where('student_status', fn ($status) => in_array(
                            $status,
                            array_map(fn (GraduationStatus $s) => $s->value, GraduationStatus::cases()),
                            strict: true,
                        ))
                        ->has('ceremony_date')
                        ->has('graduation_date')))
                ->has('filters', fn ($f) => $f->has('professor_id'))
                ->has('filter_options', fn ($o) => $o->has('professors')),
        );
});
