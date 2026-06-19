<?php

declare(strict_types=1);

use App\Domain\Ceremony\Events\CeremonyScheduled;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Feature coverage for ceremony scheduling — the 7 → 8 transition (CONTRACT §5,
 * ScheduleCeremonyAction). The happy path stores ceremony_date + ceremony_location,
 * advances JuryAssigned → CeremonyScheduled and dispatches BOTH
 * StudentStatusChanged(7→8) and CeremonyScheduled. Every guard (past/weekend/
 * out-of-hours date, missing location, wrong source state, non-staff) blocks the
 * write and leaves the student untouched. Runs against PostgreSQL 18 via
 * RefreshDatabase. Web validation surfaces as 302 + session errors, never 422.
 */

/** A future weekday at the given H:i, in the Y-m-d H:i shape the form posts. */
function schedulableDateAt(int $hour = 10, int $minute = 0): string
{
    $date = Carbon::now()->addWeeks(2)->setTime($hour, $minute, 0);

    while ($date->isWeekend()) {
        $date->addDay();
    }

    return $date->format('Y-m-d H:i');
}

/**
 * The valid schedule payload for a future weekday business-hours ceremony.
 *
 * @return array<string, string>
 */
function schedulePayload(?string $date = null, string $location = 'Auditorio Central'): array
{
    return [
        'ceremony_date' => $date ?? schedulableDateAt(),
        'ceremony_location' => $location,
    ];
}

it('schedules a ceremony, advances 7→8 and dispatches both events', function (): void {
    Event::fake([StudentStatusChanged::class, CeremonyScheduled::class]);

    $admin = User::factory()->admin()->create();
    $student = Student::factory()->juryAssigned()->create();

    actingAs($admin)
        ->post(route('admin.graduation.ceremony.schedule', $student), schedulePayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    $student->refresh();

    expect($student->status)->toBe(GraduationStatus::CeremonyScheduled)
        ->and($student->ceremony_location)->toBe('Auditorio Central')
        ->and($student->ceremony_date)->not->toBeNull();

    Event::assertDispatched(
        StudentStatusChanged::class,
        fn (StudentStatusChanged $event): bool => $event->student->is($student)
            && $event->from === GraduationStatus::JuryAssigned
            && $event->to === GraduationStatus::CeremonyScheduled,
    );
    Event::assertDispatched(
        CeremonyScheduled::class,
        fn (CeremonyScheduled $event): bool => $event->student->is($student),
    );
});

it('persists the ceremony date as a Carbon datetime', function (): void {
    $admin = User::factory()->admin()->create();
    $student = Student::factory()->juryAssigned()->create();
    $date = schedulableDateAt(14, 30);

    actingAs($admin)
        ->post(route('admin.graduation.ceremony.schedule', $student), schedulePayload($date))
        ->assertRedirect();

    expect($student->fresh()->ceremony_date)->toBeInstanceOf(Carbon::class)
        ->and($student->fresh()->ceremony_date->format('Y-m-d H:i'))->toBe($date);
});

it('rejects a past ceremony date as a session error (302), no advance, no event', function (): void {
    Event::fake([StudentStatusChanged::class, CeremonyScheduled::class]);

    $admin = User::factory()->admin()->create();
    $student = Student::factory()->juryAssigned()->create();
    $past = Carbon::now()->subWeek()->setTime(10, 0)->format('Y-m-d H:i');

    actingAs($admin)
        ->post(route('admin.graduation.ceremony.schedule', $student), schedulePayload($past))
        ->assertRedirect()
        ->assertSessionHasErrors('ceremony_date');

    expect($student->fresh()->status)->toBe(GraduationStatus::JuryAssigned)
        ->and($student->fresh()->ceremony_date)->toBeNull();

    Event::assertNotDispatched(StudentStatusChanged::class);
    Event::assertNotDispatched(CeremonyScheduled::class);
});

it('rejects a weekend ceremony date as a session error', function (): void {
    $admin = User::factory()->admin()->create();
    $student = Student::factory()->juryAssigned()->create();
    $saturday = Carbon::now()->addWeek()->next(Carbon::SATURDAY)->setTime(10, 0)->format('Y-m-d H:i');

    actingAs($admin)
        ->post(route('admin.graduation.ceremony.schedule', $student), schedulePayload($saturday))
        ->assertRedirect()
        ->assertSessionHasErrors('ceremony_date');

    expect($student->fresh()->status)->toBe(GraduationStatus::JuryAssigned);
});

it('rejects an out-of-hours ceremony date as a session error', function (
    int $hour,
    int $minute,
): void {
    $admin = User::factory()->admin()->create();
    $student = Student::factory()->juryAssigned()->create();

    actingAs($admin)
        ->post(
            route('admin.graduation.ceremony.schedule', $student),
            schedulePayload(schedulableDateAt($hour, $minute)),
        )
        ->assertRedirect()
        ->assertSessionHasErrors('ceremony_date');

    expect($student->fresh()->status)->toBe(GraduationStatus::JuryAssigned);
})->with([
    'before 08:00 (07:59)' => [7, 59],
    'at 18:00' => [18, 0],
]);

it('rejects a missing ceremony location as a session error', function (): void {
    $admin = User::factory()->admin()->create();
    $student = Student::factory()->juryAssigned()->create();

    $payload = schedulePayload();
    unset($payload['ceremony_location']);

    actingAs($admin)
        ->post(route('admin.graduation.ceremony.schedule', $student), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors('ceremony_location');

    expect($student->fresh()->status)->toBe(GraduationStatus::JuryAssigned);
});

it('blocks scheduling from a wrong source state (illegal transition, graceful 302)', function (): void {
    Event::fake([StudentStatusChanged::class, CeremonyScheduled::class]);

    $admin = User::factory()->admin()->create();
    // PaymentPending is not a legal predecessor of CeremonyScheduled.
    $student = Student::factory()->paymentVerified()->create();

    actingAs($admin)
        ->post(route('admin.graduation.ceremony.schedule', $student), schedulePayload())
        ->assertRedirect()
        ->assertSessionHasErrors('status');

    expect($student->fresh()->status)->toBe(GraduationStatus::PaymentPending)
        ->and($student->fresh()->ceremony_date)->toBeNull();

    Event::assertNotDispatched(StudentStatusChanged::class);
    Event::assertNotDispatched(CeremonyScheduled::class);
});

it('forbids a non-staff user from scheduling a ceremony', function (): void {
    $intruder = User::factory()->student()->create();
    $student = Student::factory()->juryAssigned()->create();

    actingAs($intruder)
        ->post(route('admin.graduation.ceremony.schedule', $student), schedulePayload())
        ->assertForbidden();

    expect($student->fresh()->status)->toBe(GraduationStatus::JuryAssigned);
});
