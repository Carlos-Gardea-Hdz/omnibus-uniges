<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Events;

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a student advances (or is bounced back) in the 9-state
 * graduation workflow. Drives the real-time progress bar on the student's
 * status page over a private per-student channel (Laravel Reverb).
 */
final class StudentStatusChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Student $student,
        public readonly GraduationStatus $from,
        public readonly GraduationStatus $to,
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
        return 'graduation.step.completed';
    }

    /**
     * Payload consumed by the student's real-time progress bar. Semantics are
     * consistent: `completed_step` is the step just left; everything else
     * describes the destination the student now sits in. `next_step` is null
     * once the terminal state (graduated) is reached.
     *
     * @return array{
     *     completed_step: string,
     *     next_step: string|null,
     *     progress: int,
     *     message: string
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'completed_step' => $this->from->value,
            'next_step' => $this->to->isTerminal() ? null : $this->to->value,
            'progress' => (int) round($this->to->step() / 9 * 100),
            'message' => $this->to->labelKey(),
        ];
    }
}
