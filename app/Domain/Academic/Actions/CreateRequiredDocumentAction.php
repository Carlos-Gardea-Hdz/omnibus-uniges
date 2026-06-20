<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Data\RequiredDocumentData;
use App\Domain\Academic\Models\RequiredDocument;
use Illuminate\Support\Facades\DB;

/**
 * Create a RequiredDocument catalog row. One business operation; the write is
 * wrapped in a transaction for convention symmetry.
 */
final class CreateRequiredDocumentAction
{
    public function handle(RequiredDocumentData $data): RequiredDocument
    {
        return DB::transaction(fn (): RequiredDocument => RequiredDocument::create([
            'name' => $data->name,
            'description' => $data->description,
            'allowed_mimes' => $data->allowed_mimes,
            'max_size_kb' => $data->max_size_kb,
        ]));
    }
}
