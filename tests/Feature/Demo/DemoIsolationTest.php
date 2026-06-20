<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\Models\StudentDocument;
use App\Domain\Identity\Enums\DemoPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Session-isolation security core (CONTRACT §16, spec §2.3 / scenarios 8-10).
 * The DemoScope global scope makes the demo sandbox airtight in BOTH directions:
 *
 *   1. A demo session sees ONLY its own demo_session_id rows — never another
 *      demo session's, never a real (NULL-tagged) student.
 *   2. Real and demo data are mutually invisible (the scope is symmetric).
 *   3. A demo session cannot mutate another session's row — it is unresolvable
 *      (404/403) under the scope.
 *
 * Each authenticated request carries the is_demo session keys so the
 * DemoSessionMiddleware populates the DemoContext the scope reads. Rows are
 * created with the scope bypassed during arrange (forceFill the tag) so the
 * fixtures are deterministic. PostgreSQL 18 via RefreshDatabase.
 */

/**
 * A demo staff user + its session payload + a FormBReview student tagged to it
 * (so the student shows up in the staff review queue).
 *
 * @return array{user: User, session: array<string, mixed>, student: Student}
 */
function demoStaffWithStudent(): array
{
    $sessionId = (string) Str::uuid7();

    $user = User::factory()->create(['role' => DemoPreset::Admin->role()]);
    $user->forceFill(['demo_session_id' => $sessionId])->save();

    $student = Student::factory()->formBReview()->create();
    $student->forceFill(['demo_session_id' => $sessionId])->save();

    return [
        'user' => $user,
        'session' => [
            'is_demo' => true,
            'demo_session_id' => $sessionId,
            'demo_preset' => DemoPreset::Admin->value,
            'demo_expires_at' => now()->addMinutes(30)->timestamp,
        ],
        'student' => $student,
    ];
}

it('shows a demo staff session only its OWN demo students in the review queue', function (): void {
    $a = demoStaffWithStudent();
    $b = demoStaffWithStudent();
    // A real, non-demo student also sitting in the same review state.
    Student::factory()->formBReview()->create();

    actingAs($a['user'])
        ->withSession($a['session'])
        ->get(route('admin.graduation.review'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Graduation/Review')
                ->has('students.data', 1)
                ->where('students.data.0.id', $a['student']->id),
        );
});

it('never leaks another demo session B student to demo session A', function (): void {
    $a = demoStaffWithStudent();
    $b = demoStaffWithStudent();

    actingAs($a['user'])
        ->withSession($a['session'])
        ->get(route('admin.graduation.review'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->where(
                'students.data',
                fn ($rows) => collect($rows)->pluck('id')->doesntContain($b['student']->id),
            ),
        );
});

it('hides every demo student from a REAL staff user (symmetric scope)', function (): void {
    $demo = demoStaffWithStudent();
    $realStudent = Student::factory()->formBReview()->create();
    $realStaff = User::factory()->admin()->create();

    actingAs($realStaff)
        ->get(route('admin.graduation.review'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.id', $realStudent->id),
        );
});

it('hides every real student from a demo staff user', function (): void {
    $demo = demoStaffWithStudent();
    // Two real students that must NOT appear in the demo queue.
    Student::factory()->formBReview()->create();
    Student::factory()->formBReview()->create();

    actingAs($demo['user'])
        ->withSession($demo['session'])
        ->get(route('admin.graduation.review'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('students.data', 1)
                ->where('students.data.0.id', $demo['student']->id),
        );
});

it('cannot mutate another demo session B child row — it is unresolvable for A', function (): void {
    $a = demoStaffWithStudent();
    $b = demoStaffWithStudent();

    // A document belonging to session B's student, tagged with B's session id and
    // sitting in B's student so it is review-eligible.
    $bStudent = $b['student'];
    $bStudent->forceFill(['status' => GraduationStatus::AnnexIiiPending->value])->save();

    $bDocument = StudentDocument::factory()->for($bStudent)->create();
    $bDocument->forceFill(['demo_session_id' => $b['session']['demo_session_id']])->save();

    // Acting as session A, attempt to approve B's document → unresolvable (404)
    // under A's scope. (EnsureRole passes; the row simply does not exist for A.)
    actingAs($a['user'])
        ->withSession($a['session'])
        ->post(route('admin.graduation.documents.approve', $bDocument))
        ->assertNotFound();

    // B's document is untouched (still its original status, no reviewer set).
    $fresh = StudentDocument::withoutGlobalScopes()->find($bDocument->id);
    expect($fresh)->not->toBeNull()
        ->and($fresh->reviewed_by)->toBeNull();
});

it('confirms the other session and real rows do physically exist (the scope, not deletion, hides them)', function (): void {
    $a = demoStaffWithStudent();
    $b = demoStaffWithStudent();
    Student::factory()->formBReview()->create();

    // Falsifiable guard: the isolation above is proven by SCOPING, not by missing
    // rows. Bypassing the scope must reveal all three students.
    expect(Student::withoutGlobalScopes()->count())->toBe(3);
});
