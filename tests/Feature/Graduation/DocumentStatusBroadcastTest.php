<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\DocumentStatus;
use App\Domain\Graduation\Events\DocumentStatusChanged;
use App\Domain\Graduation\Models\StudentDocument;
use Illuminate\Broadcasting\PrivateChannel;

covers(DocumentStatusChanged::class);

/*
 * The broadcast contract drives the real-time document badges over the SAME
 * private per-student channel as the status tracker (CONTRACT §6, §11). No
 * database is needed: we assert on the event's broadcast shape using an
 * in-memory StudentDocument with forced ids.
 */

/** Build the event for a document carrying the given ids, without persisting. */
function documentEvent(
    int $documentId,
    int $studentId,
    int $requiredDocumentId,
    DocumentStatus $status,
    bool $allApproved,
    ?string $rejectionReason = null,
): DocumentStatusChanged {
    $document = new StudentDocument;
    $document->id = $documentId;
    $document->student_id = $studentId;
    $document->required_document_id = $requiredDocumentId;
    $document->status = $status;
    $document->rejection_reason = $rejectionReason;

    return new DocumentStatusChanged($document, $allApproved);
}

it('broadcasts on the private per-student channel', function (): void {
    $event = documentEvent(10, 42, 3, DocumentStatus::Uploaded, false);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-student.42');
});

it('broadcasts under the document.status.changed event name', function (): void {
    $event = documentEvent(1, 1, 1, DocumentStatus::Approved, true);

    expect($event->broadcastAs())->toBe('document.status.changed');
});

it('carries the document id, required-doc id, status value, reason and all_approved=false', function (): void {
    $event = documentEvent(
        documentId: 10,
        studentId: 42,
        requiredDocumentId: 3,
        status: DocumentStatus::Rejected,
        allApproved: false,
        rejectionReason: 'Ilegible.',
    );

    expect($event->broadcastWith())
        ->toHaveKeys(['document_id', 'required_document_id', 'status', 'rejection_reason', 'all_approved'])
        ->toMatchArray([
            'document_id' => 10,
            'required_document_id' => 3,
            'status' => 'rejected',
            'rejection_reason' => 'Ilegible.',
            'all_approved' => false,
        ]);
});

it('carries all_approved=true and a null reason when the gate closes on approval', function (): void {
    $event = documentEvent(
        documentId: 11,
        studentId: 7,
        requiredDocumentId: 5,
        status: DocumentStatus::Approved,
        allApproved: true,
    );

    expect($event->broadcastWith())
        ->toMatchArray([
            'document_id' => 11,
            'required_document_id' => 5,
            'status' => 'approved',
            'rejection_reason' => null,
            'all_approved' => true,
        ]);
});
