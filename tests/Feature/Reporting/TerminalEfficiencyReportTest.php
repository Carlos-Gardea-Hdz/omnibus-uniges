<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Program;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * REPORT-01 — terminal efficiency (spec 008 §3 scenario 4, §2.4
 * Reporting/TerminalEfficiency). For each cohort (ENROLLMENT year, D-YEAR) and
 * each program, rate = round(graduates / total * 100) as a strict PHP INT 0..100
 * (D-RATE-INT), with a total===0 → rate 0 guard. graduates + total ship alongside
 * as ints. The aggregate runs on the DemoScope-scoped Student via
 * count(*) filter (where status = 'graduated'). The assertions pin the int type
 * (toBeInt) so a float 40.0 or a "40%" string would fail. Boots the app +
 * PostgreSQL 18 (RefreshDatabase).
 */

/**
 * Enrol $total students in a given cohort year (and optionally a program),
 * graduating exactly $graduated of them. The enrollment_date is pinned so the
 * cohort year is deterministic; graduates also carry a graduation_date.
 */
function seedCohort(int $cohortYear, int $total, int $graduated, ?Program $program = null): void
{
    $programId = $program?->id;
    $enrollmentDate = sprintf('%d-09-01', $cohortYear);

    // for-loops, NOT range(1, $n): range(1, 0) returns [1, 0] in PHP (a 2-element
    // descending array), which would seed 2 rows for a 0 count — the bug that made
    // a "0 graduates" cohort report a non-zero rate.
    for ($i = 0; $i < $graduated; $i++) {
        Student::factory()->ceremonyPassed()->create(array_filter([
            'status' => GraduationStatus::Graduated,
            'enrollment_date' => $enrollmentDate,
            'graduation_date' => sprintf('%d-07-01', $cohortYear + 4),
            'program_id' => $programId,
        ], fn ($v) => $v !== null));
    }

    for ($i = 0; $i < $total - $graduated; $i++) {
        Student::factory()->formBReview()->create(array_filter([
            'enrollment_date' => $enrollmentDate,
            'program_id' => $programId,
        ], fn ($v) => $v !== null));
    }
}

it('computes the int-percent rate per cohort (10 enrolled, 4 graduated → 40)', function (): void {
    $admin = User::factory()->admin()->create();
    seedCohort(cohortYear: 2019, total: 10, graduated: 4);

    actingAs($admin)
        ->get(route('admin.reports.terminal-efficiency'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/TerminalEfficiency')
                ->where('by_cohort', function ($rows): bool {
                    $row = collect($rows)->firstWhere('cohort_year', 2019);

                    return $row !== null
                        && $row['total'] === 10
                        && $row['graduates'] === 4
                        // STRICT int 40 — never 40.0, never "40%".
                        && $row['rate'] === 40
                        && is_int($row['rate']);
                }),
        );
});

it('rounds the rate to an integer and guards a zero-graduate cohort with rate 0', function (): void {
    $admin = User::factory()->admin()->create();

    // 3 of 7 graduated → 42.857.. → rounds to 43.
    seedCohort(cohortYear: 2020, total: 7, graduated: 3);
    // A cohort with zero graduates → rate must be 0, never a divide-by error.
    seedCohort(cohortYear: 2021, total: 5, graduated: 0);

    actingAs($admin)
        ->get(route('admin.reports.terminal-efficiency'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/TerminalEfficiency')
                ->where('by_cohort', function ($rows): bool {
                    $byYear = collect($rows)->keyBy('cohort_year');

                    return $byYear[2020]['rate'] === 43
                        && is_int($byYear[2020]['rate'])
                        && $byYear[2021]['graduates'] === 0
                        && $byYear[2021]['rate'] === 0
                        && is_int($byYear[2021]['rate']);
                }),
        );
});

it('orders cohorts by year descending', function (): void {
    $admin = User::factory()->admin()->create();
    seedCohort(cohortYear: 2018, total: 2, graduated: 1);
    seedCohort(cohortYear: 2022, total: 2, graduated: 1);
    seedCohort(cohortYear: 2020, total: 2, graduated: 1);

    actingAs($admin)
        ->get(route('admin.reports.terminal-efficiency'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/TerminalEfficiency')
                ->where('by_cohort', fn ($rows) => collect($rows)
                    ->pluck('cohort_year')->all() === [2022, 2020, 2018]),
        );
});

it('computes the int-percent rate per program', function (): void {
    $admin = User::factory()->admin()->create();
    $programA = Program::factory()->create(['name' => 'Programa A']);
    $programB = Program::factory()->create(['name' => 'Programa B']);

    // Program A: 5 total, 2 graduated → 40. Program B: 4 total, 3 graduated → 75.
    seedCohort(cohortYear: 2019, total: 5, graduated: 2, program: $programA);
    seedCohort(cohortYear: 2019, total: 4, graduated: 3, program: $programB);

    actingAs($admin)
        ->get(route('admin.reports.terminal-efficiency'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/TerminalEfficiency')
                ->where('by_program', function ($rows) use ($programA, $programB): bool {
                    $byId = collect($rows)->keyBy('program_id');

                    return $byId[$programA->id]['total'] === 5
                        && $byId[$programA->id]['graduates'] === 2
                        && $byId[$programA->id]['rate'] === 40
                        && $byId[$programA->id]['program_name'] === 'Programa A'
                        && is_int($byId[$programA->id]['rate'])
                        && $byId[$programB->id]['total'] === 4
                        && $byId[$programB->id]['graduates'] === 3
                        && $byId[$programB->id]['rate'] === 75
                        && is_int($byId[$programB->id]['rate']);
                }),
        );
});

it('computes the overall int-percent rate across all scoped students', function (): void {
    $admin = User::factory()->admin()->create();

    // 4 + 6 enrolled = 10 total; 1 + 4 = 5 graduated → 50%.
    seedCohort(cohortYear: 2019, total: 4, graduated: 1);
    seedCohort(cohortYear: 2020, total: 6, graduated: 4);

    actingAs($admin)
        ->get(route('admin.reports.terminal-efficiency'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/TerminalEfficiency')
                ->where('overall.total', 10)
                ->where('overall.graduates', 5)
                ->where('overall.rate', 50)
                ->where('overall', fn ($overall) => is_int($overall['rate'])),
        );
});

it('reports a zero overall rate when no students exist', function (): void {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get(route('admin.reports.terminal-efficiency'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/TerminalEfficiency')
                ->has('by_cohort', 0)
                ->has('by_program', 0)
                ->where('overall.total', 0)
                ->where('overall.graduates', 0)
                ->where('overall.rate', 0)
                ->where('overall', fn ($overall) => is_int($overall['rate'])),
        );
});
