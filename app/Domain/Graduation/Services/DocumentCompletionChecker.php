<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Services;

use App\Domain\Graduation\Enums\DocumentStatus;
use App\Domain\Graduation\Models\Student;

/**
 * Decides whether a student has satisfied every required document for their
 * graduation type — the gate for the AnnexIiiPending (5) → PaymentPending (6)
 * transition (SPEC §6.3.13).
 *
 * Empty-set policy: a graduation type with no required documents returns false,
 * so a student is never auto-advanced past a stage that has nothing to approve.
 */
final readonly class DocumentCompletionChecker
{
    public function allRequiredApproved(Student $student): bool
    {
        $requiredIds = $student->graduationType
            ->requiredDocuments()
            ->pluck('required_documents.id')
            ->all();

        if ($requiredIds === []) {
            return false;
        }

        $approvedIds = $student->documents()
            ->where('status', DocumentStatus::Approved)
            ->pluck('required_document_id')
            ->all();

        foreach ($requiredIds as $requiredId) {
            if (! in_array($requiredId, $approvedIds, strict: true)) {
                return false;
            }
        }

        return true;
    }
}
