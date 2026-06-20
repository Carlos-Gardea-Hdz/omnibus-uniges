<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Domain\Identity\Enums\DemoPreset;
use App\Domain\Jury\Models\JuryAssignment;
use App\Models\User;
use Database\Factories\StudentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * CRITICAL demo-isolation guarantee for EVERY report (spec 008 §3 scenarios 8-9,
 * D-SCOPE; the security gate). Because each report aggregates over the
 * DemoScope-scoped Student / JuryAssignment WITHOUT withoutGlobalScopes(), a demo
 * coordinator's reports count ONLY their own demo_session_id rows — never real
 * (NULL-tagged) rows, never another demo session B's. The guarantee is symmetric:
 * a real coordinator's reports exclude every demo-tagged row. If a service ever
 * bypassed the scope to "see all data", these tests go red — that bypass would
 * leak real graduate counts / real students' PII into a demo report.
 *
 * The falsifiable guard proves the isolation is by SCOPING, not by missing data:
 * Student::withoutGlobalScopes()->count() (and the jury equivalent) physically
 * exceed every demo report total — the rows exist, the scope hides them.
 *
 * Mirrors the DemoAdminDashboardIsolationTest fixture shape. Boots the app +
 * PostgreSQL 18 (RefreshDatabase).
 */

/**
 * A demo admin + its session payload.
 *
 * @return array{user: User, session: array<string, mixed>, id: string}
 */
function demoReportAdmin(): array
{
    $sessionId = (string) Str::uuid7();

    $user = User::factory()->create(['role' => DemoPreset::Admin->role()]);
    $user->forceFill(['demo_session_id' => $sessionId])->save();

    return [
        'user' => $user,
        'id' => $sessionId,
        'session' => [
            'is_demo' => true,
            'demo_session_id' => $sessionId,
            'demo_preset' => DemoPreset::Admin->value,
            'demo_expires_at' => now()->addMinutes(30)->timestamp,
        ],
    ];
}

/**
 * Create $count students in a factory state, all tagged to one demo session.
 *
 * @param  callable(StudentFactory): StudentFactory  $state
 * @return list<Student>
 */
function demoReportStudents(string $sessionId, int $count, callable $state): array
{
    $made = [];

    foreach (range(1, $count) as $ignored) {
        $student = $state(Student::factory())->create();
        $student->forceFill(['demo_session_id' => $sessionId])->save();
        $made[] = $student;
    }

    return $made;
}

/** Seat a jury for a student, tagging the assignment to the same demo session. */
function demoReportJury(string $sessionId, Student $student): JuryAssignment
{
    $assignment = JuryAssignment::factory()->create(['student_id' => $student->id]);
    $assignment->forceFill(['demo_session_id' => $sessionId])->save();

    return $assignment;
}

it('counts ONLY the acting demo session graduates in the graduates report', function (): void {
    $a = demoReportAdmin();

    // Session A: 2 graduates.
    demoReportStudents($a['id'], 2, fn ($f) => $f->ceremonyPassed()->state(fn () => [
        'status' => GraduationStatus::Graduated,
        'graduation_date' => now()->toDateString(),
    ]));

    // Another demo session B + real graduates — all invisible to A.
    $b = demoReportAdmin();
    demoReportStudents($b['id'], 3, fn ($f) => $f->ceremonyPassed()->state(fn () => [
        'status' => GraduationStatus::Graduated,
        'graduation_date' => now()->toDateString(),
    ]));
    Student::factory()->count(4)->ceremonyPassed()->create([
        'status' => GraduationStatus::Graduated,
        'graduation_date' => now()->toDateString(),
    ]);

    actingAs($a['user'])
        ->withSession($a['session'])
        ->get(route('admin.reports.graduates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Graduates')
                ->where('total', 2) // only A's 2, never B's 3, never the 4 real
                ->has('graduates', 2),
        );

    // Falsifiable: 2 + 3 + 4 = 9 graduates physically present; the report saw 2.
    expect(Student::withoutGlobalScopes()->count())
        ->toBe(9)
        ->toBeGreaterThan(2);
});

it('counts ONLY the acting demo session students in terminal efficiency', function (): void {
    $a = demoReportAdmin();

    // Session A: 4 enrolled 2019, 2 graduated → rate 50.
    demoReportStudents($a['id'], 2, fn ($f) => $f->ceremonyPassed()->state(fn () => [
        'status' => GraduationStatus::Graduated,
        'enrollment_date' => '2019-09-01',
        'graduation_date' => '2023-07-01',
    ]));
    demoReportStudents($a['id'], 2, fn ($f) => $f->formBReview()->state(fn () => [
        'enrollment_date' => '2019-09-01',
    ]));

    // Real + session-B noise that would skew the rate if leaked.
    Student::factory()->count(10)->formBReview()->create(['enrollment_date' => '2019-09-01']);
    $b = demoReportAdmin();
    demoReportStudents($b['id'], 5, fn ($f) => $f->ceremonyPassed()->state(fn () => [
        'status' => GraduationStatus::Graduated,
        'enrollment_date' => '2019-09-01',
        'graduation_date' => '2023-07-01',
    ]));

    actingAs($a['user'])
        ->withSession($a['session'])
        ->get(route('admin.reports.terminal-efficiency'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/TerminalEfficiency')
                ->where('overall.total', 4)
                ->where('overall.graduates', 2)
                ->where('overall.rate', 50), // 2/4, not contaminated by 10 real + 5 B
        );

    expect(Student::withoutGlobalScopes()->count())
        ->toBe(19) // 4 + 10 + 5
        ->toBeGreaterThan(4);
});

it('counts ONLY the acting demo session cohorts in the cohorts report', function (): void {
    $a = demoReportAdmin();
    demoReportStudents($a['id'], 3, fn ($f) => $f->formBReview()->state(fn () => [
        'enrollment_date' => '2020-09-01',
    ]));

    // Real + B noise in the SAME cohort year — must not inflate A's count.
    Student::factory()->count(6)->formBReview()->create(['enrollment_date' => '2020-09-01']);
    $b = demoReportAdmin();
    demoReportStudents($b['id'], 4, fn ($f) => $f->formBReview()->state(fn () => [
        'enrollment_date' => '2020-09-01',
    ]));

    actingAs($a['user'])
        ->withSession($a['session'])
        ->get(route('admin.reports.cohorts'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Cohorts')
                ->has('cohorts', 1)
                ->where('cohorts.0.cohort_year', 2020)
                ->where('cohorts.0.total', 3), // only A's 3
        );

    expect(Student::withoutGlobalScopes()->count())->toBe(13)->toBeGreaterThan(3);
});

it('lists ONLY the acting demo session juries in the judge report', function (): void {
    $a = demoReportAdmin();

    // Session A: one jury (its professors are catalog rows, shared & unscoped).
    $studentA = demoReportStudents($a['id'], 1, fn ($f) => $f->juryAssigned())[0];
    demoReportJury($a['id'], $studentA);

    // Real jury + session-B jury — both physically present, both invisible to A.
    JuryAssignment::factory()->create(['student_id' => Student::factory()->juryAssigned()->create()->id]);
    $b = demoReportAdmin();
    $studentB = demoReportStudents($b['id'], 1, fn ($f) => $f->juryAssigned())[0];
    demoReportJury($b['id'], $studentB);

    actingAs($a['user'])
        ->withSession($a['session'])
        ->get(route('admin.reports.judge-certificates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/JudgeCertificates')
                // Exactly the professors of A's single jury (≤4) and nobody else.
                ->where('professors', fn ($professors) => collect($professors)
                    ->sum('assignment_count') === collect($professors)->count()
                    && collect($professors)->count() <= 4
                    && collect($professors)->count() >= 3),
        );

    // 3 juries physically present (A + real + B); A's report drew from 1.
    expect(JuryAssignment::withoutGlobalScopes()->count())->toBe(3)->toBeGreaterThan(1);
});

it('counts ONLY real data for a real coordinator (symmetric isolation)', function (): void {
    // Real graduates the real admin should count.
    Student::factory()->count(3)->ceremonyPassed()->create([
        'status' => GraduationStatus::Graduated,
        'graduation_date' => now()->toDateString(),
    ]);

    // Demo noise that must stay invisible to the real admin.
    $demo = demoReportAdmin();
    demoReportStudents($demo['id'], 5, fn ($f) => $f->ceremonyPassed()->state(fn () => [
        'status' => GraduationStatus::Graduated,
        'graduation_date' => now()->toDateString(),
    ]));

    $realAdmin = User::factory()->admin()->create();

    actingAs($realAdmin)
        ->get(route('admin.reports.graduates'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Reporting/Graduates')
                ->where('total', 3) // the 3 real graduates, never the 5 demo
                ->has('graduates', 3),
        );

    expect(Student::withoutGlobalScopes()->count())->toBe(8)->toBeGreaterThan(3);
});
