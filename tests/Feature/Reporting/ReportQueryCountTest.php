<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Domain\Jury\Models\JuryAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Single-aggregate / no-N+1 guarantee per report (spec 008 §3 scenario 7, the
 * performance gate / Ley 9). Each report's data must come from ONE
 * aggregate/list query (+ eager loads) — never a per-cohort, per-status or
 * per-professor loop. Mirrors the slice-007 DB::listen technique: we count the
 * queries hitting the report's source table and assert the bound, and a
 * falsifiable guard proves the per-row loops never happen (eager loads are
 * counted but are not flagged as N+1). Boots the app + PostgreSQL 18.
 */

/**
 * Capture every SQL statement issued during the given GET, lowercased.
 *
 * @return list<string>
 */
function capturedSql(User $actor, string $routeName): array
{
    /** @var list<string> $sql */
    $sql = [];

    DB::listen(function ($query) use (&$sql): void {
        $sql[] = strtolower($query->sql);
    });

    actingAs($actor)->get(route($routeName))->assertOk();

    return $sql;
}

it('builds the graduates report from a single list query over students', function (): void {
    $admin = User::factory()->admin()->create();
    $program = Program::factory()->create();
    Student::factory()->count(5)->ceremonyPassed()->create([
        'program_id' => $program->id,
        'status' => GraduationStatus::Graduated,
        'graduation_date' => '2025-01-01',
    ]);

    $sql = capturedSql($admin, 'admin.reports.graduates');

    // Exactly one SELECT list query over students that is NOT an aggregate
    // (the row list). The distinct-years query is a separate aggregate.
    $listQueries = array_filter(
        $sql,
        fn (string $q) => str_contains($q, ' from "students"')
            && ! str_contains($q, 'count(')
            && ! str_contains($q, 'extract('),
    );

    expect($listQueries)->toHaveCount(1);
});

it('builds terminal efficiency from one grouped aggregate per axis (cohort, program, overall)', function (): void {
    $admin = User::factory()->admin()->create();
    $program = Program::factory()->create();
    Student::factory()->count(4)->ceremonyPassed()->create([
        'program_id' => $program->id,
        'status' => GraduationStatus::Graduated,
        'enrollment_date' => '2019-09-01',
        'graduation_date' => '2023-07-01',
    ]);
    Student::factory()->count(6)->formBReview()->create([
        'program_id' => $program->id,
        'enrollment_date' => '2019-09-01',
    ]);

    $sql = capturedSql($admin, 'admin.reports.terminal-efficiency');

    // Aggregate queries over students (count(*) filter ...). One each for cohort,
    // program and overall = 3 — never a per-cohort or per-program loop.
    $aggregates = array_filter(
        $sql,
        fn (string $q) => str_contains($q, ' from "students"') && str_contains($q, 'count('),
    );

    expect($aggregates)->toHaveCount(3);
});

it('builds the cohorts report from one grouped year-by-status aggregate over students', function (): void {
    $admin = User::factory()->admin()->create();
    Student::factory()->count(3)->formBReview()->create(['enrollment_date' => '2020-09-01']);
    Student::factory()->count(2)->documentsStage()->create(['enrollment_date' => '2021-09-01']);

    $sql = capturedSql($admin, 'admin.reports.cohorts');

    $aggregates = array_filter(
        $sql,
        fn (string $q) => str_contains($q, ' from "students"')
            && str_contains($q, 'group by')
            && str_contains($q, 'count('),
    );

    // ONE group-by aggregate produces every cohort × status bucket; the pivot is PHP.
    expect($aggregates)->toHaveCount(1);
});

it('builds the judge report from a single jury_assignments query with eager loads (no per-professor loop)', function (): void {
    $admin = User::factory()->admin()->create();

    // Two juries with eight distinct professors — a per-professor loop would
    // explode the query count; the eager-loaded inversion keeps it bounded.
    JuryAssignment::factory()->create([
        'student_id' => Student::factory()->juryAssigned()->create()->id,
    ]);
    JuryAssignment::factory()->create([
        'student_id' => Student::factory()->juryAssigned()->create()->id,
    ]);

    $sql = capturedSql($admin, 'admin.reports.judge-certificates');

    // Exactly one primary query over jury_assignments (the eager loads hit
    // students / programs / professors, not jury_assignments again).
    $juryQueries = array_filter(
        $sql,
        fn (string $q) => str_contains($q, ' from "jury_assignments"'),
    );

    expect($juryQueries)->toHaveCount(1);

    // Falsifiable N+1 guard: the professors are loaded via a bounded number of
    // eager-load `where in` queries, NOT one lookup per professor row. With 2
    // juries (≤8 professors) a per-row loop would emit ≥8 single-id professor
    // lookups; the eager load emits a handful of whereIn batches.
    $professorLookups = array_filter(
        $sql,
        fn (string $q) => str_contains($q, ' from "professors"'),
    );

    expect(count($professorLookups))->toBeLessThan(8);
});
