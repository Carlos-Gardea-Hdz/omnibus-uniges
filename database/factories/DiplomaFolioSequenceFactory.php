<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academic\Models\Program;
use App\Domain\Ceremony\Models\DiplomaFolioSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiplomaFolioSequence>
 */
class DiplomaFolioSequenceFactory extends Factory
{
    protected $model = DiplomaFolioSequence::class;

    /**
     * A fresh sequence for the current year, starting at zero.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'year' => now()->year,
            'last_value' => 0,
        ];
    }

    /** Pre-seed the counter at a given value (e.g. to test sequential continuation). */
    public function withValue(int $value): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_value' => $value,
        ]);
    }
}
