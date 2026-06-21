<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Actions;

use App\Domain\Graduation\Data\ReviewDocumentData;
use App\Domain\Graduation\Enums\DocumentStatus;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\DocumentStatusChanged;
use App\Domain\Graduation\Exceptions\DocumentReviewNotAllowedException;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\Models\StudentDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reviewer rejects a single uploaded document with a reason. No state-machine
 * transition (the student stays in AnnexIiiPending and must re-upload). Emits
 * the real-time document event.
 */
final class RejectDocumentAction
{
    public function handle(StudentDocument $document, ReviewDocumentData $data, User $reviewer): StudentDocument
    {
        return DB::transaction(function () use ($document, $data, $reviewer): StudentDocument {
            // Entry-state gate (+ serialize concurrent reviews): re-load the
            // owning student under a pessimistic lock and re-read state from it.
            // Documents may only be reviewed while the student is in the
            // document stage (AnnexIiiPending); a direct {studentDocument} bind
            // on a later-stage student is rejected cleanly, never silently
            // mutated.
            $student = Student::query()
                ->whereKey($document->student_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($student->status !== GraduationStatus::AnnexIiiPending) {
                throw DocumentReviewNotAllowedException::notInReviewStage($student->status);
            }

            $document->status = DocumentStatus::Rejected;
            $document->rejection_reason = $data->rejection_reason;
            $document->reviewed_by = $reviewer->id;
            $document->reviewed_at = now();
            $document->save();

            DocumentStatusChanged::dispatch($document, false);

            return $document;
        });
    }
}
