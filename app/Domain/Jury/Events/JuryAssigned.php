<?php

declare(strict_types=1);

namespace App\Domain\Jury\Events;

use App\Domain\Jury\Models\JuryAssignment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a student's jury is assigned (the 6 -> 7 transition). Drives
 * the real-time update on the student's status page over the existing private
 * per-student channel (Laravel Reverb).
 */
final class JuryAssigned implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly JuryAssignment $jury,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('student.'.$this->jury->student_id)];
    }

    public function broadcastAs(): string
    {
        return 'jury.assigned';
    }

    /**
     * @return array{
     *     student_id: int,
     *     president_professor_id: int,
     *     secretary_professor_id: int,
     *     vocal_professor_id: int,
     *     substitute_professor_id: int|null
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'student_id' => $this->jury->student_id,
            'president_professor_id' => $this->jury->president_professor_id,
            'secretary_professor_id' => $this->jury->secretary_professor_id,
            'vocal_professor_id' => $this->jury->vocal_professor_id,
            'substitute_professor_id' => $this->jury->substitute_professor_id,
        ];
    }
}
