<?php

declare(strict_types=1);

use App\Domain\Ceremony\Events\CeremonyScheduled;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Carbon;

covers(CeremonyScheduled::class);

/*
 * The broadcast contract drives the real-time ceremony notification over the SAME
 * private per-student channel as the status tracker (CONTRACT §6). No database is
 * needed: we assert on the event's broadcast shape using an in-memory Student
 * with forced attributes (mirrors StatusBroadcastTest / JuryAssignedBroadcastTest).
 */

/** Build the event for a student carrying the given attributes, without persisting. */
function ceremonyScheduledEvent(
    int $studentId,
    ?Carbon $ceremonyDate,
    ?string $location,
    GraduationStatus $status = GraduationStatus::CeremonyScheduled,
): CeremonyScheduled {
    $student = new Student;
    $student->id = $studentId;
    $student->ceremony_date = $ceremonyDate;
    $student->ceremony_location = $location;
    $student->status = $status;

    return new CeremonyScheduled($student);
}

it('broadcasts on the private per-student channel', function (): void {
    $event = ceremonyScheduledEvent(42, Carbon::parse('2026-12-04 10:00:00'), 'Auditorio');

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-student.42');
});

it('broadcasts under the ceremony.scheduled event name', function (): void {
    $event = ceremonyScheduledEvent(1, Carbon::parse('2026-12-04 10:00:00'), 'Auditorio');

    expect($event->broadcastAs())->toBe('ceremony.scheduled');
});

it('carries the student id, ISO ceremony date, location and status value', function (): void {
    $date = Carbon::parse('2026-12-04 10:00:00');
    $event = ceremonyScheduledEvent(7, $date, 'Auditorio Central');

    expect($event->broadcastWith())
        ->toHaveKeys(['student_id', 'ceremony_date', 'ceremony_location', 'status'])
        ->toMatchArray([
            'student_id' => 7,
            'ceremony_date' => $date->toIso8601String(),
            'ceremony_location' => 'Auditorio Central',
            'status' => GraduationStatus::CeremonyScheduled->value,
        ]);
});

it('carries the status as a string value, never the enum object', function (): void {
    $event = ceremonyScheduledEvent(3, Carbon::parse('2026-12-04 10:00:00'), 'Auditorio');

    expect($event->broadcastWith()['status'])->toBe('ceremony_scheduled');
});
