<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Data\ProgramData;
use App\Domain\Academic\Models\Program;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create a Program catalog row (belongs to a Department). One business
 * operation; the write is wrapped in a transaction for convention symmetry. Code
 * uniqueness is guarded here (a 302 field error on a duplicate) instead of via a
 * DTO Unique attribute, so one route-agnostic DTO serves both store and update.
 */
final class CreateProgramAction
{
    public function handle(ProgramData $data): Program
    {
        $this->assertCodeAvailable($data->code);

        return DB::transaction(fn (): Program => Program::create([
            'code' => $data->code,
            'name' => $data->name,
            'department_id' => $data->department_id,
        ]));
    }

    private function assertCodeAvailable(string $code): void
    {
        if (Program::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => __('catalogs.error.code_taken'),
            ]);
        }
    }
}
