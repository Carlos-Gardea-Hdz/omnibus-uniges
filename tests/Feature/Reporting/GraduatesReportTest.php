<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\StudyPlan;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * REPORT-02 — graduates report data correctness (spec 008 §3 scenarios 2-3,
 * §2.4 Reporting/Graduates). The read-only GraduatesReportService lists ONLY
 * Graduated students (DemoScope-scoped) with their full record, eager-loaded to
 * program + graduationType (no N+1), ordered graduation_date desc then
 * control_number. The year filter is the GRADUATION year (D-YEAR); the program
 * filter is program_id. Filters are plain GET query params on a read-only
 * endpoint — a garbage ?year=abc returns 200 with an empty/unfiltered result,
 * NEVER 422/500 (D-FILTER). Boots the app + PostgreSQL 18 (RefreshDatabase).
 */

/**
 * Mint a graduated student with a deterministic graduation date + record,
 * pinned to the given program + graduation type so the row shape is exact.
 */
function makeGraduate(
    Program $program,
    GraduationType $type,
    string $graduationDate,
    string $controlNumber,
    string $diplomaFolio,
): Student {
    return Student::factory()
        ->ceremonyPassed()
        ->create([
            'program_id' => $program->id,
            // Tie the study plan to this program so the factory chain doesn't mint
            // extra Programs (StudyPlanFactory defaults program_id to a new one),
            // which would pollute the program catalog the filter exposes.
            'study_plan_id' => StudyPlan::factory()->for($program)->create()->id,
            'graduation_type_id' => $type->id,
            'status' => GraduationStatus::Graduated,
            'graduation_date' => $graduationDate,
            'control_number' => $controlNumber,
            'diploma_folio' => $diplomaFolio,
            'record_book' => '12',
            'record_sheet' => '34',
            'first_name' => 'Ana',
            'last_name' => 'García',
        ]);
}

it('lists only Graduated students with the exact row shape', function (): void {
    $admin = User::factory()->admin()->create();
    $program = Program::factory()->create(['name' => 'Ingeniería en Sistemas Computacionales']);
    $type = GraduationType::factory()->create(['name' => 'Tesis']);

    $graduate = makeGraduate($program, $type, '2025-07-10', '20250003', '2025-PRG-001');

    // Pipeline noise that must NEVER appear in a graduates report.
    Student::factory()->count(3)->formBReview()->create();
    Student::factory()->count(2)->ceremonyScheduled()->create();

    actingAs($admin)
        ->get(route('admin.reports.graduates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Graduates')
                ->has('graduates', 1)
                ->where('total', 1)
                ->where('graduates.0.id', $graduate->id)
                ->where('graduates.0.control_number', '20250003')
                ->where('graduates.0.full_name', 'Ana García')
                ->where('graduates.0.program_name', 'Ingeniería en Sistemas Computacionales')
                ->where('graduates.0.graduation_type_name', 'Tesis')
                ->where('graduates.0.diploma_folio', '2025-PRG-001')
                ->where('graduates.0.record_book', '12')
                ->where('graduates.0.record_sheet', '34')
                ->where('graduates.0.graduation_date', '2025-07-10'),
        );
});

it('never lists a non-graduated student', function (): void {
    $admin = User::factory()->admin()->create();

    // Every non-terminal stage seeded; none may surface.
    Student::factory()->formBReview()->create();
    Student::factory()->documentsStage()->create();
    Student::factory()->paymentVerified()->create();
    Student::factory()->juryAssigned()->create();
    Student::factory()->ceremonyScheduled()->create();

    actingAs($admin)
        ->get(route('admin.reports.graduates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Graduates')
                ->has('graduates', 0)
                ->where('total', 0),
        );
});

it('orders graduates by graduation_date desc then control_number', function (): void {
    $admin = User::factory()->admin()->create();
    $program = Program::factory()->create();
    $type = GraduationType::factory()->create();

    makeGraduate($program, $type, '2024-01-05', '20250011', '2024-PRG-001');
    makeGraduate($program, $type, '2025-12-20', '20250004', '2025-PRG-002');
    makeGraduate($program, $type, '2025-12-20', '20250002', '2025-PRG-003'); // same date, lower control no.

    actingAs($admin)
        ->get(route('admin.reports.graduates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Graduates')
                ->has('graduates', 3)
                // Newest graduation date first; within the same date, lower control number first.
                ->where('graduates.0.control_number', '20250002')
                ->where('graduates.1.control_number', '20250004')
                ->where('graduates.2.control_number', '20250011'),
        );
});

it('filters by graduation year (server-side) and reflects the applied filter', function (): void {
    $admin = User::factory()->admin()->create();
    $program = Program::factory()->create();
    $type = GraduationType::factory()->create();

    makeGraduate($program, $type, '2025-03-01', '20250006', '2025-PRG-010');
    makeGraduate($program, $type, '2025-09-09', '20250007', '2025-PRG-011');
    makeGraduate($program, $type, '2024-06-15', '20250005', '2024-PRG-012');

    actingAs($admin)
        ->get(route('admin.reports.graduates', ['year' => 2025]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Graduates')
                ->has('graduates', 2)
                ->where('total', 2)
                ->where('filters.year', 2025)
                ->where('filters.program_id', null)
                ->where('graduates', fn ($rows) => collect($rows)
                    ->pluck('graduation_date')
                    ->every(fn ($d) => str_starts_with((string) $d, '2025'))),
        );
});

it('filters by program_id (server-side)', function (): void {
    $admin = User::factory()->admin()->create();
    $type = GraduationType::factory()->create();
    $programA = Program::factory()->create(['name' => 'Programa A']);
    $programB = Program::factory()->create(['name' => 'Programa B']);

    makeGraduate($programA, $type, '2025-01-10', '20250012', '2025-PRGA-001');
    makeGraduate($programA, $type, '2025-02-10', '20250013', '2025-PRGA-002');
    makeGraduate($programB, $type, '2025-03-10', '20250015', '2025-PRGB-001');

    actingAs($admin)
        ->get(route('admin.reports.graduates', ['program_id' => $programB->id]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Graduates')
                ->has('graduates', 1)
                ->where('total', 1)
                ->where('filters.program_id', $programB->id)
                ->where('graduates.0.control_number', '20250015')
                ->where('graduates.0.program_name', 'Programa B'),
        );
});

it('combines the year and program filters', function (): void {
    $admin = User::factory()->admin()->create();
    $type = GraduationType::factory()->create();
    $programA = Program::factory()->create();
    $programB = Program::factory()->create();

    makeGraduate($programA, $type, '2025-04-01', '20250014', '2025-PRGA-100');
    makeGraduate($programB, $type, '2025-05-01', '20250017', '2025-PRGB-100');
    makeGraduate($programB, $type, '2024-05-01', '20250016', '2024-PRGB-100');

    actingAs($admin)
        ->get(route('admin.reports.graduates', ['year' => 2025, 'program_id' => $programB->id]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Graduates')
                ->has('graduates', 1)
                ->where('graduates.0.control_number', '20250017'),
        );
});

it('exposes filter options: distinct graduation years (desc) and the program catalog', function (): void {
    $admin = User::factory()->admin()->create();
    $type = GraduationType::factory()->create();
    $programA = Program::factory()->create(['name' => 'Alfa']);
    $programB = Program::factory()->create(['name' => 'Beta']);

    makeGraduate($programA, $type, '2023-01-01', '20250008', '2023-PRG-1');
    makeGraduate($programA, $type, '2025-01-01', '20250009', '2025-PRG-1');
    makeGraduate($programB, $type, '2025-02-01', '20250010', '2025-PRG-2');

    actingAs($admin)
        ->get(route('admin.reports.graduates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Graduates')
                // Distinct graduation years, newest first, no duplicate 2025.
                ->where('filter_options.years', [2025, 2023])
                // The program catalog (unscoped) is offered for the <select>.
                ->where('filter_options.programs', fn ($programs) => collect($programs)
                    ->pluck('name')->all() === ['Alfa', 'Beta']),
        );
});

it('returns 200 with an empty/unfiltered result for a garbage year (D-FILTER, never 422)', function (): void {
    $admin = User::factory()->admin()->create();
    $program = Program::factory()->create();
    $type = GraduationType::factory()->create();
    makeGraduate($program, $type, '2025-01-01', '20250001', '2025-PRG-1');

    actingAs($admin)
        ->get(route('admin.reports.graduates', ['year' => 'abc']))
        ->assertOk() // never 422/500 — a read-only display filter has no validation surface
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Graduates')
                // 'abc' coerces to 0 → matches no graduation year → empty result.
                ->where('total', 0)
                ->has('graduates', 0),
        );
});

it('returns 200 with an empty result for a non-existent program id (D-FILTER)', function (): void {
    $admin = User::factory()->admin()->create();
    $program = Program::factory()->create();
    $type = GraduationType::factory()->create();
    makeGraduate($program, $type, '2025-01-01', '20250001', '2025-PRG-1');

    actingAs($admin)
        ->get(route('admin.reports.graduates', ['program_id' => 999_999]))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Graduates')
                ->where('total', 0)
                ->has('graduates', 0),
        );
});
