<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Exceptions\CatalogInUseException;
use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Graduation\Models\StudentDocument;
use Illuminate\Support\Facades\DB;

/**
 * Delete a RequiredDocument catalog row. RequiredDocument has NO SoftDeletes, so
 * this is a HARD delete — the riskiest catalog for a restrict-FK 500. A document
 * referenced by any StudentDocument (required_document_id, restrict) is refused
 * gracefully by the pre-check before any mutation, so the restrict FK is never
 * tripped and no 500 can reach the user; the graduation_type_required_document
 * pivot CASCADES, so it is not part of the in-use check.
 *
 * Reading StudentDocument here is a legitimate cross-domain READ (Academic ↛
 * Identity is the kept rule). No withoutGlobalScopes(): runs only as super_admin
 * (no demo context → DemoScope no-op → sees all real student documents).
 */
final class DeleteRequiredDocumentAction
{
    public function handle(RequiredDocument $requiredDocument): void
    {
        if ($this->isReferenced($requiredDocument)) {
            throw new CatalogInUseException(__('catalogs.error.in_use'));
        }

        DB::transaction(static fn (): ?bool => $requiredDocument->delete());
    }

    private function isReferenced(RequiredDocument $requiredDocument): bool
    {
        return StudentDocument::query()
            ->where('required_document_id', $requiredDocument->getKey())
            ->exists();
    }
}
