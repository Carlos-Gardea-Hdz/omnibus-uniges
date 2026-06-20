<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Exceptions\CatalogInUseException;
use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\StudyPlan;
use App\Domain\Ceremony\Models\DiplomaFolioSequence;
use App\Domain\Graduation\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Delete a Program catalog row (soft delete). A program referenced by any
 * Student (program_id), StudyPlan (program_id) or DiplomaFolioSequence
 * (program_id) is refused gracefully: the pre-check throws CatalogInUseException
 * before any mutation, so no dangling reference is ever left behind a soft delete
 * (which, being an UPDATE, would not trip the restrict FK).
 *
 * Reading Student/StudyPlan/DiplomaFolioSequence here is a legitimate cross-domain
 * READ (the arch rule kept is Academic ↛ Identity, not "only Shared"). No
 * withoutGlobalScopes(): the Action runs only as super_admin (no demo context →
 * scope no-op → sees all).
 */
final class DeleteProgramAction
{
    public function handle(Program $program): void
    {
        if ($this->isReferenced($program)) {
            throw new CatalogInUseException(__('catalogs.error.in_use'));
        }

        DB::transaction(static fn (): ?bool => $program->delete());
    }

    private function isReferenced(Program $program): bool
    {
        $id = $program->getKey();

        return Student::query()->where('program_id', $id)->exists()
            || StudyPlan::query()->where('program_id', $id)->exists()
            || DiplomaFolioSequence::query()->where('program_id', $id)->exists();
    }
}
