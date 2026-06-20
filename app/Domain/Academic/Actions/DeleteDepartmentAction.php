<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Exceptions\CatalogInUseException;
use App\Domain\Academic\Models\Department;
use App\Domain\Academic\Models\Program;
use Illuminate\Support\Facades\DB;

/**
 * Delete a Department catalog row (soft delete). A department referenced by any
 * Program is refused gracefully: the pre-check throws CatalogInUseException
 * (rendered as a 302 + friendly field error in bootstrap/app.php) before any
 * mutation, so the restrict FK is never tripped and no 500 can reach the user.
 *
 * The pre-check intentionally does NOT call withoutGlobalScopes(): this Action
 * runs only under the super_admin gate (no demo context), so the DemoScope is a
 * no-op and the query already sees every real child row.
 */
final class DeleteDepartmentAction
{
    public function handle(Department $department): void
    {
        if ($this->isReferenced($department)) {
            throw new CatalogInUseException(__('catalogs.error.in_use'));
        }

        DB::transaction(static fn (): ?bool => $department->delete());
    }

    private function isReferenced(Department $department): bool
    {
        return Program::query()
            ->where('department_id', $department->getKey())
            ->exists();
    }
}
