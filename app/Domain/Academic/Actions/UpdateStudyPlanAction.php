<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Data\StudyPlanData;
use App\Domain\Academic\Models\StudyPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update a StudyPlan catalog row. Unique-ignore-self on code is done here (a
 * whereKeyNot() pre-check → 302 field error on clash with any other row),
 * keeping the DTO route-agnostic.
 */
final class UpdateStudyPlanAction
{
    public function handle(StudyPlan $studyPlan, StudyPlanData $data): StudyPlan
    {
        $this->assertCodeAvailable($data->code, $studyPlan);

        return DB::transaction(function () use ($studyPlan, $data): StudyPlan {
            $studyPlan->fill([
                'code' => $data->code,
                'name' => $data->name,
                'program_id' => $data->program_id,
            ])->save();

            return $studyPlan;
        });
    }

    private function assertCodeAvailable(string $code, StudyPlan $studyPlan): void
    {
        $taken = StudyPlan::query()
            ->where('code', $code)
            ->whereKeyNot($studyPlan->getKey())
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'code' => __('catalogs.error.code_taken'),
            ]);
        }
    }
}
