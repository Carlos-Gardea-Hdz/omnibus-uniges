<?php

declare(strict_types=1);

use App\Domain\Ceremony\Events\StudentGraduated;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Feature coverage for graduation completion — the 8 → 9 TERMINAL transition
 * (CONTRACT §5, MarkAsGraduatedAction). The happy path requires a ceremony date
 * that has already PASSED: it mints a diploma_folio ({YEAR}-{CODE}-{SEQ}), sets
 * record_book/record_sheet + graduation_date, advances CeremonyScheduled →
 * Graduated and dispatches BOTH StudentStatusChanged(8→9) (next_step null at the
 * terminal) and StudentGraduated. Guards (future/null ceremony_date, wrong source
 * state, non-staff) block the write and mint no folio. PostgreSQL 18 via
 * RefreshDatabase; web validation = 302 + session errors.
 */

it('graduates a student, advances 8→9 (terminal) and assigns a folio', function (): void {
    Event::fake([StudentStatusChanged::class, StudentGraduated::class]);

    $admin = User::factory()->admin()->create();
    $student = Student::factory()->ceremonyPassed()->create();

    actingAs($admin)
        ->post(route('admin.graduation.ceremony.graduate', $student))
        ->assertRedirect()
        ->assertSessionHas('success');

    $student->refresh();

    expect($student->status)->toBe(GraduationStatus::Graduated)
        ->and($student->diploma_folio)->toMatch('/^\d{4}-[A-Z0-9]+-\d{3}$/')
        ->and($student->record_book)->not->toBeNull()
        ->and($student->record_sheet)->not->toBeNull()
        ->and($student->graduation_date)->not->toBeNull()
        ->and($student->graduation_date->toDateString())->toBe(now()->toDateString());

    Event::assertDispatched(
        StudentStatusChanged::class,
        fn (StudentStatusChanged $event): bool => $event->student->is($student)
            && $event->from === GraduationStatus::CeremonyScheduled
            && $event->to === GraduationStatus::Graduated,
    );
    Event::assertDispatched(
        StudentGraduated::class,
        fn (StudentGraduated $event): bool => $event->student->is($student),
    );
});

it('rejects graduating an already-graduated student — terminal, no second folio', function (): void {
    $admin = User::factory()->admin()->create();
    $student = Student::factory()->ceremonyPassed()->create();

    // First graduation succeeds (8→9).
    actingAs($admin)->post(route('admin.graduation.ceremony.graduate', $student))->assertRedirect();
    $folio = $student->fresh()->diploma_folio;
    expect($folio)->not->toBeNull();

    // Second attempt: Graduated is terminal → the state machine rejects 9→9,
    // mapped to a graceful 302; no new folio is minted and no event fires.
    Event::fake([StudentStatusChanged::class, StudentGraduated::class]);

    actingAs($admin)
        ->post(route('admin.graduation.ceremony.graduate', $student))
        ->assertRedirect();

    expect($student->fresh()->diploma_folio)->toBe($folio);
    Event::assertNotDispatched(StudentStatusChanged::class);
    Event::assertNotDispatched(StudentGraduated::class);
});

it('emits a null next_step on the terminal StudentStatusChanged broadcast', function (): void {
    Event::fake([StudentStatusChanged::class, StudentGraduated::class]);

    $admin = User::factory()->admin()->create();
    $student = Student::factory()->ceremonyPassed()->create();

    actingAs($admin)
        ->post(route('admin.graduation.ceremony.graduate', $student))
        ->assertRedirect();

    Event::assertDispatched(
        StudentStatusChanged::class,
        fn (StudentStatusChanged $event): bool => $event->broadcastWith()['next_step'] === null
            && $event->to === GraduationStatus::Graduated,
    );
});

it('derives record_book from the year and record_sheet as the padded sequence', function (): void {
    $admin = User::factory()->admin()->create();
    $student = Student::factory()->ceremonyPassed()->create();

    actingAs($admin)
        ->post(route('admin.graduation.ceremony.graduate', $student))
        ->assertRedirect();

    $student->refresh();
    /** @var string $folio */
    $folio = $student->diploma_folio;
    [$year, , $seq] = explode('-', $folio);

    expect($student->record_book)->toBe($year)
        ->and($student->record_sheet)->toBe($seq);
});

it('blocks graduation while the ceremony date is still in the future', function (): void {
    Event::fake([StudentStatusChanged::class, StudentGraduated::class]);

    $admin = User::factory()->admin()->create();
    // ceremonyScheduled() pins a FUTURE ceremony date — graduation must be blocked.
    $student = Student::factory()->ceremonyScheduled()->create();

    actingAs($admin)
        ->post(route('admin.graduation.ceremony.graduate', $student))
        ->assertRedirect()
        ->assertSessionHasErrors('ceremony_date');

    $student->refresh();

    expect($student->status)->toBe(GraduationStatus::CeremonyScheduled)
        ->and($student->diploma_folio)->toBeNull()
        ->and($student->graduation_date)->toBeNull();

    Event::assertNotDispatched(StudentStatusChanged::class);
    Event::assertNotDispatched(StudentGraduated::class);
});

it('blocks graduation when no ceremony date is set', function (): void {
    $admin = User::factory()->admin()->create();
    // A student in CeremonyScheduled but with a null ceremony_date (defensive guard).
    $student = Student::factory()->juryAssigned()->create([
        'status' => GraduationStatus::CeremonyScheduled->value,
        'ceremony_date' => null,
    ]);

    actingAs($admin)
        ->post(route('admin.graduation.ceremony.graduate', $student))
        ->assertRedirect()
        ->assertSessionHasErrors('ceremony_date');

    expect($student->fresh()->status)->toBe(GraduationStatus::CeremonyScheduled)
        ->and($student->fresh()->diploma_folio)->toBeNull();
});

it('blocks graduation from a wrong source state (graceful 302, no folio)', function (): void {
    Event::fake([StudentStatusChanged::class, StudentGraduated::class]);

    $admin = User::factory()->admin()->create();
    // JuryAssigned is not a legal predecessor of Graduated. A PAST ceremony_date
    // clears the not_passed precondition so the STATE MACHINE is what blocks it.
    $student = Student::factory()->ceremonyPassed()->create([
        'status' => GraduationStatus::JuryAssigned->value,
    ]);

    actingAs($admin)
        ->post(route('admin.graduation.ceremony.graduate', $student))
        ->assertRedirect()
        ->assertSessionHasErrors('status');

    $student->refresh();

    expect($student->status)->toBe(GraduationStatus::JuryAssigned)
        ->and($student->diploma_folio)->toBeNull();

    Event::assertNotDispatched(StudentStatusChanged::class);
    Event::assertNotDispatched(StudentGraduated::class);
});

it('forbids a non-staff user from marking a student graduated', function (): void {
    $intruder = User::factory()->student()->create();
    $student = Student::factory()->ceremonyPassed()->create();

    actingAs($intruder)
        ->post(route('admin.graduation.ceremony.graduate', $student))
        ->assertForbidden();

    expect($student->fresh()->status)->toBe(GraduationStatus::CeremonyScheduled)
        ->and($student->fresh()->diploma_folio)->toBeNull();
});
