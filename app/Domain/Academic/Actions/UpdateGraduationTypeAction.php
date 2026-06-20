<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Data\GraduationTypeData;
use App\Domain\Academic\Models\GraduationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update a GraduationType catalog row AND re-sync its required documents.
 * Unique-ignore-self on code is checked here; the row update and the
 * graduation_type_required_document pivot re-sync (which removes dropped rows)
 * span two tables, so both run inside one transaction.
 */
final class UpdateGraduationTypeAction
{
    public function handle(GraduationType $graduationType, GraduationTypeData $data): GraduationType
    {
        $this->assertCodeAvailable($data->code, $graduationType);

        return DB::transaction(function () use ($graduationType, $data): GraduationType {
            $graduationType->fill([
                'code' => $data->code,
                'name' => $data->name,
                'requires_advisor' => $data->requires_advisor,
            ])->save();

            $graduationType->requiredDocuments()->sync($data->required_document_ids);

            return $graduationType;
        });
    }

    private function assertCodeAvailable(string $code, GraduationType $graduationType): void
    {
        $taken = GraduationType::query()
            ->where('code', $code)
            ->whereKeyNot($graduationType->getKey())
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'code' => __('catalogs.error.code_taken'),
            ]);
        }
    }
}
