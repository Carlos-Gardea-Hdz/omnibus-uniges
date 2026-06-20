<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Exceptions\CatalogInUseException;
use App\Domain\Academic\Models\Professor;
use App\Domain\Jury\Models\JuryAssignment;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Delete a Professor catalog row (soft delete). A professor sitting on any jury
 * as president / secretary / vocal (restrict FKs) is refused gracefully. The
 * advisor and substitute roles are nullOnDelete, NOT restrict, so a professor
 * used only as an advisor or substitute deletes successfully (those columns are
 * nulled by the database). The pre-check throws CatalogInUseException before any
 * mutation, so the restrict FK is never tripped and no 500 can reach the user.
 *
 * Reading JuryAssignment here is a legitimate cross-domain READ (Academic ↛
 * Identity is the kept rule). No withoutGlobalScopes(): runs only as super_admin
 * (no demo context → DemoScope no-op → sees all real jury rows).
 */
final class DeleteProfessorAction
{
    public function handle(Professor $professor): void
    {
        if ($this->isReferenced($professor)) {
            throw new CatalogInUseException(__('catalogs.error.in_use'));
        }

        DB::transaction(static fn (): ?bool => $professor->delete());
    }

    private function isReferenced(Professor $professor): bool
    {
        $id = $professor->getKey();

        // The OR group is wrapped in a nested closure so it combines with the
        // DemoScope's top-level `demo_session_id IS NULL` predicate via AND, not
        // OR (an un-grouped orWhere would leak every real jury row regardless of
        // the professor, making exists() always true).
        return JuryAssignment::query()
            ->where(function (Builder $query) use ($id): void {
                $query
                    ->where('president_professor_id', $id)
                    ->orWhere('secretary_professor_id', $id)
                    ->orWhere('vocal_professor_id', $id);
            })
            ->exists();
    }
}
