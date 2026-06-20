<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Exceptions\CatalogInUseException;
use App\Domain\Academic\Models\StudyPlan;
use App\Domain\Graduation\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Delete a StudyPlan catalog row (soft delete). A plan referenced by any Student
 * (study_plan_id, restrict) is refused gracefully. The pre-check throws
 * CatalogInUseException before any mutation, so the restrict FK is never tripped
 * and no 500 can reach the user.
 *
 * Reading Student here is a legitimate cross-domain READ (Academic ↛ Identity is
 * the kept rule). No withoutGlobalScopes(): runs only as super_admin (no demo
 * context → DemoScope no-op → sees all real students).
 */
final class DeleteStudyPlanAction
{
    public function handle(StudyPlan $studyPlan): void
    {
        if ($this->isReferenced($studyPlan)) {
            throw new CatalogInUseException(__('catalogs.error.in_use'));
        }

        DB::transaction(static fn (): ?bool => $studyPlan->delete());
    }

    private function isReferenced(StudyPlan $studyPlan): bool
    {
        return Student::query()
            ->where('study_plan_id', $studyPlan->getKey())
            ->exists();
    }
}
