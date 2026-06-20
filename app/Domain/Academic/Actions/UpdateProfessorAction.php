<?php

declare(strict_types=1);

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Data\ProfessorData;
use App\Domain\Academic\Models\Professor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update a Professor catalog row. The unique column is email, so unique-ignore-
 * self is done here (a whereKeyNot() pre-check → 302 field error on clash with
 * any other professor's email), keeping the DTO route-agnostic.
 */
final class UpdateProfessorAction
{
    public function handle(Professor $professor, ProfessorData $data): Professor
    {
        $this->assertEmailAvailable($data->email, $professor);

        return DB::transaction(function () use ($professor, $data): Professor {
            $professor->fill([
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'mother_last_name' => $data->mother_last_name,
                'email' => $data->email,
            ])->save();

            return $professor;
        });
    }

    private function assertEmailAvailable(string $email, Professor $professor): void
    {
        $taken = Professor::query()
            ->where('email', $email)
            ->whereKeyNot($professor->getKey())
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'email' => __('catalogs.error.email_taken'),
            ]);
        }
    }
}
