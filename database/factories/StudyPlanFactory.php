<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\StudyPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StudyPlan>
 */
class StudyPlanFactory extends Factory
{
    protected $model = StudyPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SP-'.Str::upper(fake()->unique()->bothify('??##')),
            'name' => 'Plan de Estudios '.fake()->unique()->numberBetween(2010, 2024),
            'program_id' => Program::factory(),
        ];
    }
}
