<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Data\StudyPlanData;
use App\Domain\Academic\Models\StudyPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create a StudyPlan catalog row (belongs to a Program). One business operation;
 * the write is wrapped in a transaction for convention symmetry. Code uniqueness
 * is guarded here (a 302 field error on a duplicate) instead of via a DTO Unique
 * attribute, so one route-agnostic DTO serves both store and update.
 */
final class CreateStudyPlanAction
{
    public function handle(StudyPlanData $data): StudyPlan
    {
        $this->assertCodeAvailable($data->code);

        return DB::transaction(fn (): StudyPlan => StudyPlan::create([
            'code' => $data->code,
            'name' => $data->name,
            'program_id' => $data->program_id,
        ]));
    }

    private function assertCodeAvailable(string $code): void
    {
        if (StudyPlan::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => __('catalogs.error.code_taken'),
            ]);
        }
    }
}
