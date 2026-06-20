<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Data\GraduationTypeData;
use App\Domain\Academic\Models\GraduationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create a GraduationType catalog row AND attach its required documents. The row
 * insert and the graduation_type_required_document pivot sync genuinely span two
 * tables, so both run inside one transaction. Code uniqueness is guarded here (a
 * 302 field error on a duplicate) instead of via a DTO Unique attribute, so one
 * route-agnostic DTO serves both store and update.
 */
final class CreateGraduationTypeAction
{
    public function handle(GraduationTypeData $data): GraduationType
    {
        $this->assertCodeAvailable($data->code);

        return DB::transaction(function () use ($data): GraduationType {
            $type = GraduationType::create([
                'code' => $data->code,
                'name' => $data->name,
                'requires_advisor' => $data->requires_advisor,
            ]);

            $type->requiredDocuments()->sync($data->required_document_ids);

            return $type;
        });
    }

    private function assertCodeAvailable(string $code): void
    {
        if (GraduationType::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => __('catalogs.error.code_taken'),
            ]);
        }
    }
}
