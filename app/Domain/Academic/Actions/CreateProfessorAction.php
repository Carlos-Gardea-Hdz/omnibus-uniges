<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Data\ProfessorData;
use App\Domain\Academic\Models\Professor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create a Professor catalog row. One business operation; the write is wrapped
 * in a transaction for convention symmetry. Email uniqueness is guarded here (a
 * 302 field error on a duplicate) instead of via a DTO Unique attribute, so one
 * route-agnostic DTO serves both store and update.
 */
final class CreateProfessorAction
{
    public function handle(ProfessorData $data): Professor
    {
        $this->assertEmailAvailable($data->email);

        return DB::transaction(fn (): Professor => Professor::create([
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'mother_last_name' => $data->mother_last_name,
            'email' => $data->email,
        ]));
    }

    private function assertEmailAvailable(string $email): void
    {
        if (Professor::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('catalogs.error.email_taken'),
            ]);
        }
    }
}
