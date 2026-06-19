<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academic\Models\Professor;
use App\Domain\Graduation\Models\Student;
use App\Domain\Jury\Models\JuryAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JuryAssignment>
 */
class JuryAssignmentFactory extends Factory
{
    protected $model = JuryAssignment::class;

    /**
     * Define the model's default state — a full jury (four distinct professors)
     * for a payment-verified student. Four professors are created up front so
     * each role gets a guaranteed-distinct id (no unique() pool overflow).
     *
     * Fictional demo data only — no real names or records.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$president, $secretary, $vocal, $substitute] = Professor::factory()
            ->count(4)
            ->create()
            ->all();

        return [
            'student_id' => Student::factory()->paymentVerified(),
            'president_professor_id' => $president->id,
            'secretary_professor_id' => $secretary->id,
            'vocal_professor_id' => $vocal->id,
            'substitute_professor_id' => $substitute->id,
        ];
    }

    /** A jury with no substitute member. */
    public function withoutSubstitute(): static
    {
        return $this->state(fn (array $attributes): array => [
            'substitute_professor_id' => null,
        ]);
    }
}
