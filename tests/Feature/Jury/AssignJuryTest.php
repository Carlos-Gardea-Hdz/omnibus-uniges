<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Professor;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Domain\Jury\Events\JuryAssigned;
use App\Domain\Jury\Models\JuryAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Feature coverage for the jury assignment — the 6 → 7 transition (CONTRACT §8,
 * AssignJuryAction). The happy path creates one jury_assignments row, advances
 * PaymentPending → JuryAssigned and dispatches BOTH StudentStatusChanged(6→7)
 * and JuryAssigned. Every guard (unverified payment, wrong source state,
 * duplicate jury, non-distinct professors, non-existent ids) blocks the write
 * and leaves the student untouched. Runs against PostgreSQL 18 via
 * RefreshDatabase. Web validation surfaces as 302 + session errors, never 422.
 */

/**
 * A student verified-and-ready for jury assignment plus four distinct
 * professors to seat. Returns [student, [p1,p2,p3,p4]].
 *
 * @return array{0: Student, 1: array<int, Professor>}
 */
function studentReadyForJury(): array
{
    $student = Student::factory()->paymentVerified()->create();
    $professors = Professor::factory()->count(4)->create()->all();

    return [$student, $professors];
}

/**
 * The four-distinct assignment payload for a set of professors.
 *
 * @param  array<int, Professor>  $professors
 * @return array<string, int|null>
 */
function juryPayload(array $professors, ?int $substitute = null): array
{
    return [
        'president_professor_id' => $professors[0]->id,
        'secretary_professor_id' => $professors[1]->id,
        'vocal_professor_id' => $professors[2]->id,
        'substitute_professor_id' => $substitute ?? $professors[3]->id,
    ];
}

it('assigns a jury, advances 6→7 and dispatches both events', function (): void {
    Event::fake([StudentStatusChanged::class, JuryAssigned::class]);

    $admin = User::factory()->admin()->create();
    [$student, $professors] = studentReadyForJury();

    actingAs($admin)
        ->post(route('admin.graduation.jury.assign', $student), juryPayload($professors))
        ->assertRedirect()
        ->assertSessionHas('success');

    $student->refresh();
    $jury = JuryAssignment::query()->where('student_id', $student->id)->sole();

    expect(JuryAssignment::query()->count())->toBe(1)
        ->and($student->status)->toBe(GraduationStatus::JuryAssigned)
        ->and($jury->president_professor_id)->toBe($professors[0]->id)
        ->and($jury->secretary_professor_id)->toBe($professors[1]->id)
        ->and($jury->vocal_professor_id)->toBe($professors[2]->id)
        ->and($jury->substitute_professor_id)->toBe($professors[3]->id);

    Event::assertDispatched(
        StudentStatusChanged::class,
        fn (StudentStatusChanged $event): bool => $event->student->is($student)
            && $event->from === GraduationStatus::PaymentPending
            && $event->to === GraduationStatus::JuryAssigned,
    );
    Event::assertDispatched(
        JuryAssigned::class,
        fn (JuryAssigned $event): bool => $event->jury->is($jury),
    );
});

it('assigns a jury without a substitute (null substitute persisted)', function (): void {
    Event::fake([StudentStatusChanged::class, JuryAssigned::class]);

    $admin = User::factory()->admin()->create();
    [$student, $professors] = studentReadyForJury();

    $payload = juryPayload($professors);
    $payload['substitute_professor_id'] = null;

    actingAs($admin)
        ->post(route('admin.graduation.jury.assign', $student), $payload)
        ->assertRedirect();

    $jury = JuryAssignment::query()->where('student_id', $student->id)->sole();

    expect($jury->substitute_professor_id)->toBeNull()
        ->and($student->fresh()->status)->toBe(GraduationStatus::JuryAssigned);

    Event::assertDispatched(JuryAssigned::class);
});

it('blocks assignment when the payment is not verified (no row, no advance, no event)', function (): void {
    Event::fake([StudentStatusChanged::class, JuryAssigned::class]);

    $admin = User::factory()->admin()->create();
    $student = Student::factory()->paymentPending()->create(['payment_verified' => false]);
    $professors = Professor::factory()->count(4)->create()->all();

    actingAs($admin)
        ->post(route('admin.graduation.jury.assign', $student), juryPayload($professors))
        ->assertRedirect()
        ->assertSessionHasErrors('payment_verified');

    expect(JuryAssignment::query()->count())->toBe(0)
        ->and($student->fresh()->status)->toBe(GraduationStatus::PaymentPending);

    Event::assertNotDispatched(StudentStatusChanged::class);
    Event::assertNotDispatched(JuryAssigned::class);
});

it('blocks assignment from a wrong source state (illegal transition, full rollback)', function (): void {
    Event::fake([StudentStatusChanged::class, JuryAssigned::class]);

    $admin = User::factory()->admin()->create();
    // A student before payment but somehow flagged verified: the state machine
    // still rejects the AnnexIiiPending → JuryAssigned edge and rolls back.
    $student = Student::factory()->create([
        'status' => GraduationStatus::AnnexIiiPending->value,
        'payment_verified' => true,
    ]);
    $professors = Professor::factory()->count(4)->create()->all();

    actingAs($admin)
        ->post(route('admin.graduation.jury.assign', $student), juryPayload($professors));

    expect(JuryAssignment::query()->count())->toBe(0)
        ->and($student->fresh()->status)->toBe(GraduationStatus::AnnexIiiPending);

    Event::assertNotDispatched(StudentStatusChanged::class);
    Event::assertNotDispatched(JuryAssigned::class);
});

it('rejects an already-assigned student (one jury per student)', function (): void {
    $admin = User::factory()->admin()->create();
    [$student, $professors] = studentReadyForJury();

    // First assignment succeeds and moves the student to step 7.
    actingAs($admin)
        ->post(route('admin.graduation.jury.assign', $student), juryPayload($professors))
        ->assertRedirect();

    // A second attempt is rejected by the state machine (JuryAssigned is no
    // longer a legal source for → JuryAssigned); the unique row guards the DB.
    $more = Professor::factory()->count(4)->create()->all();

    actingAs($admin)
        ->post(route('admin.graduation.jury.assign', $student), juryPayload($more));

    expect(JuryAssignment::query()->where('student_id', $student->id)->count())->toBe(1);
});

it('rejects two equal professor ids as a session error (302), no row written', function (
    string $duplicateField,
): void {
    $admin = User::factory()->admin()->create();
    [$student, $professors] = studentReadyForJury();

    $payload = juryPayload($professors);
    // Collide the chosen field with the president.
    $payload[$duplicateField] = $professors[0]->id;

    actingAs($admin)
        ->post(route('admin.graduation.jury.assign', $student), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors();

    expect(JuryAssignment::query()->count())->toBe(0)
        ->and($student->fresh()->status)->toBe(GraduationStatus::PaymentPending);
})->with([
    'secretary == president' => ['secretary_professor_id'],
    'vocal == president' => ['vocal_professor_id'],
    'substitute == president' => ['substitute_professor_id'],
]);

it('rejects a missing required professor id as a session error', function (): void {
    $admin = User::factory()->admin()->create();
    [$student, $professors] = studentReadyForJury();

    $payload = juryPayload($professors);
    unset($payload['vocal_professor_id']);

    actingAs($admin)
        ->post(route('admin.graduation.jury.assign', $student), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors('vocal_professor_id');

    expect(JuryAssignment::query()->count())->toBe(0);
});

it('rejects a non-existent professor id as a session error', function (): void {
    $admin = User::factory()->admin()->create();
    [$student, $professors] = studentReadyForJury();

    $payload = juryPayload($professors);
    $payload['vocal_professor_id'] = 999_999;

    actingAs($admin)
        ->post(route('admin.graduation.jury.assign', $student), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors('vocal_professor_id');

    expect(JuryAssignment::query()->count())->toBe(0);
});

it('forbids a non-staff user from assigning a jury', function (): void {
    $intruder = User::factory()->student()->create();
    [$student, $professors] = studentReadyForJury();

    actingAs($intruder)
        ->post(route('admin.graduation.jury.assign', $student), juryPayload($professors))
        ->assertForbidden();

    expect(JuryAssignment::query()->count())->toBe(0);
});
