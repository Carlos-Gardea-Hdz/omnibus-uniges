<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\Professor;
use App\Domain\Academic\Models\Program;
use App\Domain\Academic\Models\StudyPlan;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * Define the model's default state.
     *
     * Fictional demo data only — no real control numbers, names or records.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'control_number' => (string) fake()->numberBetween(10_000_000, 999_999_999),
            'program_id' => Program::factory(),
            'graduation_type_id' => GraduationType::factory(),
            'study_plan_id' => StudyPlan::factory(),
            'advisor_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'mother_last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'age' => fake()->numberBetween(21, 35),
            'phone' => fake()->numerify('##########'),
            'mobile' => fake()->numerify('##########'),
            'status' => GraduationStatus::FormBPending,
            'workflow_metadata' => null,
            'gpa' => fake()->randomFloat(2, 70, 100),
            'enrollment_date' => fake()->dateTimeBetween('-6 years', '-4 years')->format('Y-m-d'),
            'graduation_date' => null,
            'thesis_title' => fake()->optional()->sentence(6),
            'thesis_abstract' => fake()->optional()->paragraph(),
            'form_b_submitted_at' => null,
            'form_b_approved' => false,
            'form_b_observations' => null,
            'annex_iii_completed' => false,
            'documents_completed_at' => null,
            'payment_reference' => null,
            'payment_verified' => false,
            'paid_at' => null,
            'ceremony_date' => null,
            'ceremony_location' => null,
            'diploma_folio' => null,
            'record_book' => null,
            'record_sheet' => null,
            'address_street' => fake()->streetName(),
            'address_neighborhood' => fake()->citySuffix(),
            'address_ext_number' => (string) fake()->numberBetween(1, 999),
            'address_int_number' => null,
            'address_postal_code' => fake()->numberBetween(10_000, 99_999),
            'is_team_project' => false,
        ];
    }

    /** Student whose Format B is awaiting review. */
    public function formBReview(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GraduationStatus::FormBReview,
            'form_b_submitted_at' => now(),
        ]);
    }

    /** Student whose Format B was rejected, with observations to address. */
    public function formBRejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GraduationStatus::FormBRejected,
            'form_b_submitted_at' => now()->subDay(),
            'form_b_observations' => fake()->sentence(10),
        ]);
    }

    /** Attach a real advisor (professor) instead of leaving it null. */
    public function withAdvisor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'advisor_id' => Professor::factory(),
        ]);
    }
}
