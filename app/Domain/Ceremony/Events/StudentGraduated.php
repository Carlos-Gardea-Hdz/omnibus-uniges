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
 * Broadcast when a student is marked graduated (the terminal 8 → 9 transition).
 * Carries the freshly minted diploma folio and graduation date, broadcast over
 * the existing private per-student channel (Laravel Reverb).
 */
final class StudentGraduated implements ShouldBroadcast
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
        return 'student.graduated';
    }

    /**
     * @return array{
     *     student_id: int,
     *     diploma_folio: string|null,
     *     graduation_date: string|null,
     *     status: string
     * }
     */
    public function broadcastWith(): array
    {
        $graduationDate = $this->student->graduation_date;

        return [
            'student_id' => $this->student->id,
            'diploma_folio' => $this->student->diploma_folio,
            'graduation_date' => $graduationDate === null
                ? null
                : Carbon::parse($graduationDate)->toIso8601String(),
            'status' => $this->student->status->value,
        ];
    }
}
