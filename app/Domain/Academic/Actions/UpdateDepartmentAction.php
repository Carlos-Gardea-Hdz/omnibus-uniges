<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Data\DepartmentData;
use App\Domain\Academic\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update a Department catalog row. Uniqueness is enforced in the Actions (not a
 * DTO attribute) so one DTO can serve both store and update: on update the row
 * must keep its OWN code, so this whereKeyNot() pre-check surfaces a clash with
 * ANY OTHER row as a 302 field error (mirroring
 * SubmitFormBAction::assertControlNumberAvailable); the DB unique index is the
 * TOCTOU backstop.
 */
final class UpdateDepartmentAction
{
    public function handle(Department $department, DepartmentData $data): Department
    {
        $this->assertCodeAvailable($data->code, $department);

        return DB::transaction(function () use ($department, $data): Department {
            $department->fill([
                'code' => $data->code,
                'name' => $data->name,
            ])->save();

            return $department;
        });
    }

    private function assertCodeAvailable(string $code, Department $department): void
    {
        $taken = Department::query()
            ->where('code', $code)
            ->whereKeyNot($department->getKey())
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'code' => __('catalogs.error.code_taken'),
            ]);
        }
    }
}
