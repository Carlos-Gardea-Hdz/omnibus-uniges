<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Data\RequiredDocumentData;
use App\Domain\Academic\Models\RequiredDocument;
use Illuminate\Support\Facades\DB;

/**
 * Update a RequiredDocument catalog row. RequiredDocument has NO unique column,
 * so there is no unique-ignore-self pre-check; the write runs inside a
 * transaction for convention symmetry.
 */
final class UpdateRequiredDocumentAction
{
    public function handle(RequiredDocument $requiredDocument, RequiredDocumentData $data): RequiredDocument
    {
        return DB::transaction(function () use ($requiredDocument, $data): RequiredDocument {
            $requiredDocument->fill([
                'name' => $data->name,
                'description' => $data->description,
                'allowed_mimes' => $data->allowed_mimes,
                'max_size_kb' => $data->max_size_kb,
            ])->save();

            return $requiredDocument;
        });
    }
}
