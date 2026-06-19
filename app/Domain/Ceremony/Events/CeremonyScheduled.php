<?php

declare(strict_types=1);

namespace App\Domain\Ceremony\Events;

use App\Domain\Graduation\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Broadcast when a student's ceremony is scheduled (the 7 → 8 transition).
 * Drives the real-time update on the student's status page over the existing
 * private per-student channel (Laravel Reverb).
 */
final class CeremonyScheduled implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Student $student,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('student.'.$this->student->id)];
    }

    public function broadcastAs(): string
    {
        return 'ceremony.scheduled';
    }

    /**
     * @return array{
     *     student_id: int,
     *     ceremony_date: string|null,
     *     ceremony_location: string|null,
     *     status: string
     * }
     */
    public function broadcastWith(): array
    {
        $ceremonyDate = $this->student->ceremony_date;

        return [
            'student_id' => $this->student->id,
            'ceremony_date' => $ceremonyDate === null
                ? null
                : Carbon::parse($ceremonyDate)->toIso8601String(),
            'ceremony_location' => $this->student->ceremony_location,
            'status' => $this->student->status->value,
        ];
    }
}
