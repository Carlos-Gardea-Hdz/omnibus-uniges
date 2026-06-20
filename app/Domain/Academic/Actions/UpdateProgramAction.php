<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Data\ProgramData;
use App\Domain\Academic\Models\Program;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update a Program catalog row. Unique-ignore-self on code is done here (a
 * whereKeyNot() pre-check → 302 field error on clash with any other row),
 * keeping the DTO route-agnostic.
 */
final class UpdateProgramAction
{
    public function handle(Program $program, ProgramData $data): Program
    {
        $this->assertCodeAvailable($data->code, $program);

        return DB::transaction(function () use ($program, $data): Program {
            $program->fill([
                'code' => $data->code,
                'name' => $data->name,
                'department_id' => $data->department_id,
            ])->save();

            return $program;
        });
    }

    private function assertCodeAvailable(string $code, Program $program): void
    {
        $taken = Program::query()
            ->where('code', $code)
            ->whereKeyNot($program->getKey())
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'code' => __('catalogs.error.code_taken'),
            ]);
        }
    }
}
