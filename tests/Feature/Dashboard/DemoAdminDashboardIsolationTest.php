<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Domain\Identity\Enums\DemoPreset;
use App\Models\User;
use Database\Factories\StudentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * CRITICAL demo-isolation guarantee for the AGGREGATE admin dashboard
 * (CONTRACT §4, §8.5; SPEC §13). The DashboardController issues ONE grouped-count
 * over `students` WITHOUT `withoutGlobalScope(DemoScope::class)`, so the DemoScope
 * must transparently confine the histogram:
 *
 *   - a demo admin counts ONLY its own demo_session_id students — never real
 *     (NULL-tagged) students, never another demo session B's;
 *   - a real admin counts ONLY real students.
 *
 * If the controller ever bypasses the scope to "see everything", these tests go
 * red — that bypass would leak real graduate counts into a demo dashboard (and
 * inflate a real dashboard with sandbox rows). Mirrors the DemoIsolationTest
 * session-payload shape. Boots the app + PostgreSQL 18 (RefreshDatabase).
 */

/**
 * A demo admin + its session payload. Students are minted separately so each
 * session can be loaded with its own stage mix.
 *
 * @return array{user: User, session: array<string, mixed>, id: string}
 */
function demoAdmin(): array
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
 * Create $count students in a given factory state, all tagged to one demo
 * session (the scope is bypassed during arrange so the fixtures are
 * deterministic).
 *
 * @param  callable(StudentFactory): StudentFactory  $state
 */
function demoStudents(string $sessionId, int $count, callable $state): void
{
    foreach (range(1, $count) as $ignored) {
        $student = $state(Student::factory())->create();
        $student->forceFill(['demo_session_id' => $sessionId])->save();
    }
}

it('counts ONLY the acting demo session students in the dashboard totals', function (): void {
    $a = demoAdmin();

    // Session A: 2 in form_b_review + 1 graduated = 3 students, 1 graduate.
    demoStudents($a['id'], 2, fn ($f) => $f->formBReview());
    demoStudents($a['id'], 1, fn ($f) => $f->ceremonyPassed()->state(fn () => [
        'status' => GraduationStatus::Graduated,
        'graduation_date' => now()->toDateString(),
    ]));

    // A different demo session B (must be invisible to A).
    $b = demoAdmin();
    demoStudents($b['id'], 5, fn ($f) => $f->formBReview());

    // Real students (NULL-tagged) — must be invisible to A.
    Student::factory()->count(4)->formBReview()->create();
    Student::factory()->count(2)->ceremonyPassed()->create([
        'status' => GraduationStatus::Graduated,
        'graduation_date' => now()->toDateString(),
    ]);

    actingAs($a['user'])
        ->withSession($a['session'])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Graduation/AdminDashboard')
                ->where('totals.students', 3)
                ->where('totals.graduates', 1)
                ->where('totals.in_progress', 2)
                // The form_b_review breakdown row sees only A's 2, not B's 5.
                ->where('status_breakdown', function ($rows): bool {
                    $byStatus = collect($rows)->keyBy('status');

                    return $byStatus['form_b_review']['count'] === 2
                        && $byStatus['graduated']['count'] === 1;
                }),
        );
});

it('never leaks real or other-session counts — falsifiable scope guard', function (): void {
    $a = demoAdmin();
    demoStudents($a['id'], 2, fn ($f) => $f->formBReview());

    // Real + session-B noise that physically exists but must not be counted.
    Student::factory()->count(6)->formBReview()->create();
    $b = demoAdmin();
    demoStudents($b['id'], 3, fn ($f) => $f->documentsStage());

    $response = actingAs($a['user'])
        ->withSession($a['session'])
        ->get(route('admin.dashboard'))
        ->assertOk();

    // The dashboard saw only A's 2 students…
    $response->assertInertia(
        fn ($page) => $page->where('totals.students', 2),
    );

    // …yet bypassing every scope reveals all 11 rows physically present — proving
    // the isolation is by SCOPING, not by missing data.
    expect(Student::withoutGlobalScopes()->count())
        ->toBe(11)
        ->toBeGreaterThan(2);
});

it('counts ONLY real students for a real admin (symmetric scope)', function (): void {
    // Real students the real admin should count.
    Student::factory()->count(3)->formBReview()->create();
    Student::factory()->count(1)->ceremonyPassed()->create([
        'status' => GraduationStatus::Graduated,
        'graduation_date' => now()->toDateString(),
    ]);

    // Demo noise that must stay invisible to the real admin.
    $demo = demoAdmin();
    demoStudents($demo['id'], 5, fn ($f) => $f->formBReview());

    $realAdmin = User::factory()->admin()->create();

    actingAs($realAdmin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Graduation/AdminDashboard')
                ->where('totals.students', 4)
                ->where('totals.graduates', 1)
                ->where('status_breakdown', fn ($rows) => collect($rows)
                    ->keyBy('status')['form_b_review']['count'] === 3),
        );
});
