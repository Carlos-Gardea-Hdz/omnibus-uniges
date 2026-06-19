<?php

declare(strict_types=1);

use App\Domain\Jury\Events\JuryAssigned;
use App\Domain\Jury\Models\JuryAssignment;
use Illuminate\Broadcasting\PrivateChannel;

covers(JuryAssigned::class);

/*
 * The broadcast contract drives the real-time jury notification over the SAME
 * private per-student channel as the status tracker and document badges
 * (CONTRACT §9, §11). No database is needed: we assert on the event's broadcast
 * shape using an in-memory JuryAssignment with forced ids (mirrors
 * DocumentStatusBroadcastTest).
 */

/** Build the event for a jury carrying the given ids, without persisting. */
function juryEvent(
    int $juryId,
    int $studentId,
    int $presidentId,
    int $secretaryId,
    int $vocalId,
    ?int $substituteId,
): JuryAssigned {
    $jury = new JuryAssignment;
    $jury->id = $juryId;
    $jury->student_id = $studentId;
    $jury->president_professor_id = $presidentId;
    $jury->secretary_professor_id = $secretaryId;
    $jury->vocal_professor_id = $vocalId;
    $jury->substitute_professor_id = $substituteId;

    return new JuryAssigned($jury);
}

it('broadcasts on the private per-student channel', function (): void {
    $event = juryEvent(5, 42, 1, 2, 3, 4);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-student.42');
});

it('broadcasts under the jury.assigned event name', function (): void {
    $event = juryEvent(1, 1, 1, 2, 3, 4);

    expect($event->broadcastAs())->toBe('jury.assigned');
});

it('carries the student id and the four professor ids', function (): void {
    $event = juryEvent(
        juryId: 5,
        studentId: 42,
        presidentId: 10,
        secretaryId: 11,
        vocalId: 12,
        substituteId: 13,
    );

    expect($event->broadcastWith())
        ->toHaveKeys([
            'student_id',
            'president_professor_id',
            'secretary_professor_id',
            'vocal_professor_id',
            'substitute_professor_id',
        ])
        ->toMatchArray([
            'student_id' => 42,
            'president_professor_id' => 10,
            'secretary_professor_id' => 11,
            'vocal_professor_id' => 12,
            'substitute_professor_id' => 13,
        ]);
});

it('carries a null substitute when none was seated', function (): void {
    $event = juryEvent(
        juryId: 6,
        studentId: 7,
        presidentId: 20,
        secretaryId: 21,
        vocalId: 22,
        substituteId: null,
    );

    expect($event->broadcastWith())
        ->toMatchArray([
            'student_id' => 7,
            'president_professor_id' => 20,
            'secretary_professor_id' => 21,
            'vocal_professor_id' => 22,
            'substitute_professor_id' => null,
        ]);
});
