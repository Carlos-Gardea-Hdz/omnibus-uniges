<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Actions;

use App\Domain\Graduation\Enums\DocumentStatus;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\Models\StudentDocument;
use App\Domain\Graduation\StateMachine\GraduationStateMachine;
use App\Domain\Shared\ValueObjects\FileToken;
use Illuminate\Support\Facades\DB;

/**
 * Opens the document-upload stage: advances AnnexesPending (4) ->
 * AnnexIiiPending (5) and idempotently seeds a pending StudentDocument row for
 * each required document of the student's graduation type. Guarded by the state
 * machine inside a transaction; emits the real-time progress event.
 */
final class BeginDocumentStageAction
{
    public function __construct(
        private readonly GraduationStateMachine $stateMachine,
    ) {}

    public function handle(Student $student): Student
    {
        return DB::transaction(function () use ($student): Student {
            $from = $student->status;
            $to = GraduationStatus::AnnexIiiPending;

            // Idempotent: re-entering an already-open stage is a no-op (no
            // re-transition, no duplicate event), not a 500 (SPEC §3.4).
            if ($from === $to) {
                return $student;
            }

            $this->stateMachine->assertCanTransition($from, $to);

            foreach ($student->graduationType->requiredDocuments as $requiredDocument) {
                StudentDocument::firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'required_document_id' => $requiredDocument->id,
                    ],
                    [
                        'status' => DocumentStatus::Pending,
                        'file_token' => (string) FileToken::generate(),
                    ],
                );
            }

            $student->status = $to;
            $student->save();

            StudentStatusChanged::dispatch($student, $from, $to);

            return $student;
        });
    }
}
