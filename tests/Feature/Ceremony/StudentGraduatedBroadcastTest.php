<?php

declare(strict_types=1);

use App\Domain\Ceremony\Events\StudentGraduated;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Carbon;

covers(StudentGraduated::class);

/*
 * The broadcast contract drives the real-time graduation notification over the
 * SAME private per-student channel as the status tracker (CONTRACT §6). No
 * database is needed: we assert on the event's broadcast shape using an in-memory
 * Student with forced attributes (mirrors JuryAssignedBroadcastTest).
 */

/** Build the event for a graduated student carrying the given attributes. */
function studentGraduatedEvent(
    int $studentId,
    ?string $folio,
    ?Carbon $graduationDate,
    GraduationStatus $status = GraduationStatus::Graduated,
): StudentGraduated {
    $student = new Student;
    $student->id = $studentId;
    $student->diploma_folio = $folio;
    $student->graduation_date = $graduationDate;
    $student->status = $status;

    return new StudentGraduated($student);
}

it('broadcasts on the private per-student channel', function (): void {
    $event = studentGraduatedEvent(42, '2026-ISC01-001', Carbon::parse('2026-12-05'));

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-student.42');
});

it('broadcasts under the student.graduated event name', function (): void {
    $event = studentGraduatedEvent(1, '2026-ISC01-001', Carbon::parse('2026-12-05'));

    expect($event->broadcastAs())->toBe('student.graduated');
});

it('carries the student id, diploma folio, ISO graduation date and status value', function (): void {
    $date = Carbon::parse('2026-12-05');
    $event = studentGraduatedEvent(7, '2026-ISC01-042', $date);

    expect($event->broadcastWith())
        ->toHaveKeys(['student_id', 'diploma_folio', 'graduation_date', 'status'])
        ->toMatchArray([
            'student_id' => 7,
            'diploma_folio' => '2026-ISC01-042',
            'graduation_date' => $date->toIso8601String(),
            'status' => GraduationStatus::Graduated->value,
        ]);
});

it('carries the status as a string value, never the enum object', function (): void {
    $event = studentGraduatedEvent(3, '2026-ISC01-001', Carbon::parse('2026-12-05'));

    expect($event->broadcastWith()['status'])->toBe('graduated');
});
