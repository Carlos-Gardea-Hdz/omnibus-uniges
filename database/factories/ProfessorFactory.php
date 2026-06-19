<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academic\Models\Professor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Professor>
 */
class ProfessorFactory extends Factory
{
    protected $model = Professor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'mother_last_name' => fake()->optional()->lastName(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }

    /**
     * Indicate that the professor has no recorded mother's last name.
     */
    public function withoutMotherLastName(): static
    {
        return $this->state(fn (array $attributes): array => [
            'mother_last_name' => null,
        ]);
    }
}
