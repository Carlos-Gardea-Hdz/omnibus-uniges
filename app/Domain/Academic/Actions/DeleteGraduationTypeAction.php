<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Exceptions\CatalogInUseException;
use App\Domain\Academic\Models\GraduationType;
use App\Domain\Graduation\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Delete a GraduationType catalog row (soft delete). A type referenced by any
 * Student (graduation_type_id, restrict) is refused gracefully. The
 * graduation_type_required_document pivot CASCADES on delete, so it is not part
 * of the in-use check. The pre-check throws CatalogInUseException before any
 * mutation, so the restrict FK is never tripped and no 500 can reach the user.
 *
 * Reading Student here is a legitimate cross-domain READ (Academic ↛ Identity is
 * the kept rule). No withoutGlobalScopes(): runs only as super_admin (no demo
 * context → DemoScope no-op → sees all real students).
 */
final class DeleteGraduationTypeAction
{
    public function handle(GraduationType $graduationType): void
    {
        if ($this->isReferenced($graduationType)) {
            throw new CatalogInUseException(__('catalogs.error.in_use'));
        }

        DB::transaction(static fn (): ?bool => $graduationType->delete());
    }

    private function isReferenced(GraduationType $graduationType): bool
    {
        return Student::query()
            ->where('graduation_type_id', $graduationType->getKey())
            ->exists();
    }
}
