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
            // Name is not a unique DB column; numberBetween(2010,2024) has only 15
            // values, so unique() exhausts past 15 rows — don't wrap it in unique().
            'name' => 'Plan de Estudios '.fake()->numberBetween(2010, 2024),
            'program_id' => Program::factory(),
        ];
    }
}
