<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * REPORT-03 — cohort overview (spec 008 §3 scenario 5, §2.4 Reporting/Cohorts).
 * Students are grouped by ENROLLMENT year (D-YEAR); each cohort row carries
 * total, graduated, in_progress (= total - graduated) and a by_status of EXACTLY
 * 9 rows in step() order (every GraduationStatus present, missing → 0) each with
 * status value, step, label_key, color, count. The aggregate is ONE
 * `group by year, status` over the DemoScope-scoped Student, pivoted in PHP.
 * Boots the app + PostgreSQL 18 (RefreshDatabase).
 */

/** Enrol a student in a deterministic cohort year, in a given factory state. */
function enrolInCohort(int $cohortYear, callable $state, array $overrides = []): Student
{
    return $state(Student::factory())->create(array_merge([
        'enrollment_date' => sprintf('%d-09-01', $cohortYear),
    ], $overrides));
}

it('groups by enrollment year with a 9-row step-ordered by_status breakdown', function (): void {
    $admin = User::factory()->admin()->create();

    // Cohort 2019: 2 in form_b_review, 1 in payment_pending, 3 graduated → 6 total, 3 graduated.
    enrolInCohort(2019, fn ($f) => $f->formBReview());
    enrolInCohort(2019, fn ($f) => $f->formBReview());
    enrolInCohort(2019, fn ($f) => $f->paymentPending());
    enrolInCohort(2019, fn ($f) => $f->ceremonyPassed(), [
        'status' => GraduationStatus::Graduated,
        'graduation_date' => '2023-07-01',
    ]);
    enrolInCohort(2019, fn ($f) => $f->ceremonyPassed(), [
        'status' => GraduationStatus::Graduated,
        'graduation_date' => '2023-07-01',
    ]);
    enrolInCohort(2019, fn ($f) => $f->ceremonyPassed(), [
        'status' => GraduationStatus::Graduated,
        'graduation_date' => '2023-07-01',
    ]);

    /** @var array<string, int> $expected */
    $expected = [
        'form_b_pending' => 0,
        'form_b_review' => 2,
        'form_b_rejected' => 0,
        'annexes_pending' => 0,
        'annex_iii_pending' => 0,
        'payment_pending' => 1,
        'jury_assigned' => 0,
        'ceremony_scheduled' => 0,
        'graduated' => 3,
    ];

    actingAs($admin)
        ->get(route('admin.reports.cohorts'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Cohorts')
                ->has('cohorts', 1)
                ->where('cohorts.0.cohort_year', 2019)
                ->where('cohorts.0.total', 6)
                ->where('cohorts.0.graduated', 3)
                ->where('cohorts.0.in_progress', 3) // 6 - 3
                ->has('cohorts.0.by_status', 9)
                ->where('cohorts.0.by_status', function ($rows) use ($expected): bool {
                    $rows = collect($rows);
                    $cases = GraduationStatus::cases();

                    foreach ($cases as $i => $case) {
                        $row = $rows[$i];

                        if ($row['status'] !== $case->value
                            || $row['step'] !== $case->step()
                            || $row['label_key'] !== $case->labelKey()
                            || $row['color'] !== $case->color()
                            || $row['count'] !== $expected[$case->value]) {
                            return false;
                        }
                    }

                    return $rows->count() === 9;
                }),
        );
});

it('produces one row per enrollment-year cohort, ordered by year descending', function (): void {
    $admin = User::factory()->admin()->create();

    enrolInCohort(2018, fn ($f) => $f->formBReview());
    enrolInCohort(2021, fn ($f) => $f->formBReview());
    enrolInCohort(2020, fn ($f) => $f->formBReview());

    actingAs($admin)
        ->get(route('admin.reports.cohorts'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Cohorts')
                ->has('cohorts', 3)
                ->where('cohorts', fn ($rows) => collect($rows)
                    ->pluck('cohort_year')->all() === [2021, 2020, 2018]),
        );
});

it('derives in_progress as total minus graduated for a cohort with no graduates', function (): void {
    $admin = User::factory()->admin()->create();

    enrolInCohort(2022, fn ($f) => $f->formBReview());
    enrolInCohort(2022, fn ($f) => $f->documentsStage());
    enrolInCohort(2022, fn ($f) => $f->juryAssigned());

    actingAs($admin)
        ->get(route('admin.reports.cohorts'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Cohorts')
                ->where('cohorts.0.cohort_year', 2022)
                ->where('cohorts.0.total', 3)
                ->where('cohorts.0.graduated', 0)
                ->where('cohorts.0.in_progress', 3),
        );
});

it('returns an empty cohort list when no students exist', function (): void {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get(route('admin.reports.cohorts'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Cohorts')
                ->has('cohorts', 0),
        );
});
