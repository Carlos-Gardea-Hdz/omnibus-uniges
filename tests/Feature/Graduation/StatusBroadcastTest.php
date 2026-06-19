<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use Illuminate\Broadcasting\PrivateChannel;

covers(StudentStatusChanged::class);

/*
 * The broadcast contract drives the student's real-time progress bar over a
 * private per-student channel (SPEC §3.3). No database is needed: we assert on
 * the event's broadcast shape using an in-memory Student with a forced id.
 */

/** Build the event for a Student carrying the given id, without persisting. */
function statusEvent(
    int $studentId,
    GraduationStatus $from,
    GraduationStatus $to,
): StudentStatusChanged {
    $student = new Student;
    $student->id = $studentId;

    return new StudentStatusChanged($student, $from, $to);
}

it('broadcasts on the private per-student channel', function (): void {
    $event = statusEvent(42, GraduationStatus::FormBPending, GraduationStatus::FormBReview);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-student.42');
});

it('broadcasts under the graduation.step.completed event name', function (): void {
    $event = statusEvent(1, GraduationStatus::FormBReview, GraduationStatus::AnnexesPending);

    expect($event->broadcastAs())->toBe('graduation.step.completed');
});

it('carries the completed step, a null next step at the terminal state, progress and message', function (): void {
    $event = statusEvent(7, GraduationStatus::CeremonyScheduled, GraduationStatus::Graduated);

    // Reaching the terminal state: there is no next step, so next_step is null.
    expect($event->broadcastWith())
        ->toHaveKeys(['completed_step', 'next_step', 'progress', 'message'])
        ->toMatchArray([
            'completed_step' => 'ceremony_scheduled',
            'next_step' => null,
            'progress' => 100,
            'message' => 'status.graduated',
        ]);
});

it('carries the destination as next_step for a non-terminal transition', function (): void {
    $event = statusEvent(7, GraduationStatus::FormBPending, GraduationStatus::FormBReview);

    expect($event->broadcastWith()['next_step'])->toBe('form_b_review');
});

it('computes progress as the target step over nine, rounded to a percentage', function (): void {
    $event = statusEvent(3, GraduationStatus::FormBPending, GraduationStatus::FormBReview);

    // FormBReview is step 2 of 9 → round(2 / 9 * 100) = 22.
    expect($event->broadcastWith()['progress'])->toBe(22);
});
