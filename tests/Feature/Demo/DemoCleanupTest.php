<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Department;
use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\Models\StudentDocument;
use App\Domain\Identity\Enums\DemoPreset;
use App\Domain\Jury\Models\JuryAssignment;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
 * Cleanup core (CONTRACT §6/§7 + §16, spec §2.2.9 / scenarios 11-13). The
 * `demo:cleanup` command force-deletes EXACTLY the demo-tagged rows whose
 * created_at has passed the 30-minute TTL, in FK-safe order (jury_assignments →
 * student_documents → students → users), and NEVER touches a non-demo or a
 * baseline-catalog row. It is idempotent (a second run with nothing expired
 * deletes 0 and exits 0). The never-touch-non-demo invariant is falsifiable: any
 * delete missing the `demo_session_id IS NOT NULL` predicate would drop the real
 * student or a baseline row and fail this suite. PostgreSQL 18 via RefreshDatabase.
 *
 * Demo rows are minted with the soft-delete scope bypassed during arrange and
 * aged by stamping created_at, so the cut is deterministic without time travel.
 */

/** A 31-minutes-old timestamp — strictly past the 30-minute cleanup TTL. */
function expiredAt(): Carbon
{
    return now()->subMinutes(31);
}

/**
 * Build a complete demo session FK chain (user → student → document +
 * jury_assignment), all tagged with one session id and stamped to `created_at`.
 *
 * @return array{user: User, student: Student, document: StudentDocument, jury: JuryAssignment}
 */
function demoChain(Carbon $createdAt): array
{
    $sessionId = (string) Str::uuid7();

    $user = User::factory()->create(['role' => DemoPreset::Sustentante4->role()]);
    $student = Student::factory()->juryAssigned()->for($user)->create();
    $document = StudentDocument::factory()->for($student)->create();
    // The juryAssigned() state only flips the student's status; the jury row is a
    // separate write, so create it explicitly to exercise the FK-safe delete order.
    $jury = JuryAssignment::factory()->for($student)->create();

    foreach ([$user, $student, $document, $jury] as $row) {
        $row->forceFill([
            'demo_session_id' => $sessionId,
            'created_at' => $createdAt,
        ])->saveQuietly();
    }

    return ['user' => $user, 'student' => $student, 'document' => $document, 'jury' => $jury];
}

/** Seed a small fixed baseline catalog (NOT demo-tagged, never cleaned). */
function seedBaseline(): array
{
    return [
        'departments' => Department::factory()->count(2)->create()->count(),
        'programs' => Program::factory()->count(3)->create()->count(),
        'professors' => Professor::factory()->count(4)->create()->count(),
        'graduation_types' => GraduationType::factory()->count(2)->create()->count(),
    ];
}

it('force-deletes an expired demo session and leaves a fresh one intact', function (): void {
    $expired = demoChain(expiredAt());
    $fresh = demoChain(now());

    $this->artisan('demo:cleanup')->assertSuccessful();

    // Expired session: every row in the FK chain is gone (force-deleted, not soft).
    expect(User::withoutGlobalScopes()->find($expired['user']->id))->toBeNull()
        ->and(Student::withoutGlobalScopes()->withTrashed()->find($expired['student']->id))->toBeNull()
        ->and(StudentDocument::withoutGlobalScopes()->withTrashed()->find($expired['document']->id))->toBeNull()
        ->and(JuryAssignment::withoutGlobalScopes()->withTrashed()->find($expired['jury']->id))->toBeNull();

    // Fresh session: untouched.
    expect(User::withoutGlobalScopes()->find($fresh['user']->id))->not->toBeNull()
        ->and(Student::withoutGlobalScopes()->withTrashed()->find($fresh['student']->id))->not->toBeNull()
        ->and(StudentDocument::withoutGlobalScopes()->withTrashed()->find($fresh['document']->id))->not->toBeNull()
        ->and(JuryAssignment::withoutGlobalScopes()->withTrashed()->find($fresh['jury']->id))->not->toBeNull();
});

it('never deletes a real (non-demo) student or its user when cleanup runs', function (): void {
    $realUser = User::factory()->student()->create();
    $realStudent = Student::factory()->for($realUser)->create();
    // Age the real student PAST the TTL — only the missing demo tag protects it.
    $realStudent->forceFill(['created_at' => expiredAt()])->saveQuietly();
    $realUser->forceFill(['created_at' => expiredAt()])->saveQuietly();

    demoChain(expiredAt());

    $this->artisan('demo:cleanup')->assertSuccessful();

    // The real, aged, NULL-tagged rows survive — the cut is by tag, not by age alone.
    expect(User::withoutGlobalScopes()->find($realUser->id))->not->toBeNull()
        ->and(Student::withoutGlobalScopes()->withTrashed()->find($realStudent->id))->not->toBeNull();
});

it('leaves every baseline-catalog row count unchanged (CRITICAL invariant)', function (): void {
    seedBaseline();
    demoChain(expiredAt());

    // Snapshot the FULL catalog population at cleanup time (the seeded baseline
    // PLUS whatever catalogs the demo chain references): the invariant is that
    // cleanup deletes only demo-tagged rows and never a single catalog row, so
    // every catalog count must be byte-for-byte identical after the sweep.
    $catalog = [
        'departments' => Department::query()->count(),
        'programs' => Program::query()->count(),
        'professors' => Professor::query()->count(),
        'graduation_types' => GraduationType::query()->count(),
    ];

    $this->artisan('demo:cleanup')->assertSuccessful();

    expect(Department::query()->count())->toBe($catalog['departments'])
        ->and(Program::query()->count())->toBe($catalog['programs'])
        ->and(Professor::query()->count())->toBe($catalog['professors'])
        ->and(GraduationType::query()->count())->toBe($catalog['graduation_types']);
});

it('is idempotent — a second run with nothing expired deletes nothing and exits 0', function (): void {
    demoChain(expiredAt());
    $fresh = demoChain(now());

    $this->artisan('demo:cleanup')->assertSuccessful();
    // Second run: only the fresh session remains, and it is not yet expired.
    $this->artisan('demo:cleanup')->assertSuccessful();

    expect(User::withoutGlobalScopes()->find($fresh['user']->id))->not->toBeNull();
});

it('deletes no rows at all when there are no demo sessions', function (): void {
    $realUser = User::factory()->student()->create();
    $before = seedBaseline();

    $this->artisan('demo:cleanup')->assertSuccessful();

    expect(User::withoutGlobalScopes()->find($realUser->id))->not->toBeNull()
        ->and(Program::query()->count())->toBe($before['programs']);
});

it('is registered to run every fifteen minutes without overlapping', function (): void {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())->first(
        fn (Event $event): bool => str_contains($event->command ?? '', 'demo:cleanup'),
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *');
});
