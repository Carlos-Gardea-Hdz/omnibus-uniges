<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Actions;

use App\Domain\Graduation\Enums\DocumentStatus;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\DocumentStatusChanged;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Exceptions\DocumentReviewNotAllowedException;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\Models\StudentDocument;
use App\Domain\Graduation\Services\DocumentCompletionChecker;
use App\Domain\Graduation\StateMachine\GraduationStateMachine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reviewer approves a single uploaded document. When this approval completes
 * the student's required-document set, advances AnnexIiiPending (5) ->
 * PaymentPending (6) in the same transaction. Guarded by the state machine;
 * emits both the document event and (when complete) the stage-progress event.
 */
final class ApproveDocumentAction
{
    public function __construct(
        private readonly GraduationStateMachine $stateMachine,
        private readonly DocumentCompletionChecker $completionChecker,
    ) {}

    public function handle(StudentDocument $document, User $reviewer): StudentDocument
    {
        return DB::transaction(function () use ($document, $reviewer): StudentDocument {
            // Entry-state gate (+ serialize concurrent reviews): re-load the
            // owning student under a pessimistic lock and re-read state from it.
            // Documents may only be reviewed while the student is in the
            // document stage (AnnexIiiPending); a direct {studentDocument} bind
            // on a later-stage student is rejected cleanly, never silently
            // mutated, and a double-click races on the lock instead of the read.
            $student = Student::query()
                ->whereKey($document->student_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($student->status !== GraduationStatus::AnnexIiiPending) {
                throw DocumentReviewNotAllowedException::notInReviewStage($student->status);
            }

            $document->status = DocumentStatus::Approved;
            $document->reviewed_by = $reviewer->id;
            $document->reviewed_at = now();
            $document->save();

            $complete = $this->completionChecker->allRequiredApproved($student);

            DocumentStatusChanged::dispatch($document, $complete);

            if ($complete) {
                $from = $student->status;
                $to = GraduationStatus::PaymentPending;

                $this->stateMachine->assertCanTransition($from, $to);

                $student->documents_completed_at = now();
                $student->status = $to;
                $student->save();

                StudentStatusChanged::dispatch($student, $from, $to);
            }

            return $document;
        });
    }
}
