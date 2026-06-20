<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Data\DepartmentData;
use App\Domain\Academic\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create a Department catalog row. One business operation; the single write is
 * wrapped in a transaction for symmetry with the multi-table catalog Actions and
 * to future-proof the convention. Code uniqueness is guarded here (a 302 field
 * error on a duplicate) instead of via a DTO Unique attribute, so the same
 * route-agnostic DTO serves both store and update — mirroring the slice-001
 * control_number convention.
 */
final class CreateDepartmentAction
{
    public function handle(DepartmentData $data): Department
    {
        $this->assertCodeAvailable($data->code);

        return DB::transaction(fn (): Department => Department::create([
            'code' => $data->code,
            'name' => $data->name,
        ]));
    }

    private function assertCodeAvailable(string $code): void
    {
        if (Department::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => __('catalogs.error.code_taken'),
            ]);
        }
    }
}
