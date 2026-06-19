<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Events;

use App\Domain\Graduation\Models\StudentDocument;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a single student document changes status (uploaded, approved
 * or rejected). Drives the real-time document checklist on the student's page
 * over the existing private per-student channel (Laravel Reverb). The
 * `all_approved` flag tells the frontend the 5 → 6 stage advance has fired.
 */
final class DocumentStatusChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly StudentDocument $document,
        public readonly bool $allApproved,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('student.'.$this->document->student_id)];
    }

    public function broadcastAs(): string
    {
        return 'document.status.changed';
    }

    /**
     * @return array{
     *     document_id: int,
     *     required_document_id: int,
     *     status: string,
     *     rejection_reason: string|null,
     *     all_approved: bool
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'document_id' => $this->document->id,
            'required_document_id' => $this->document->required_document_id,
            'status' => $this->document->status->value,
            'rejection_reason' => $this->document->rejection_reason,
            'all_approved' => $this->allApproved,
        ];
    }
}
