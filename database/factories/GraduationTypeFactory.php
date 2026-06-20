<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academic\Models\GraduationType;
use App\Domain\Academic\Models\RequiredDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GraduationType>
 */
class GraduationTypeFactory extends Factory
{
    protected $model = GraduationType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'GT-'.Str::upper(fake()->unique()->bothify('??##')),
            // Name is not a unique DB column; don't exhaust a tiny unique() pool.
            'name' => fake()->randomElement([
                'Tesis',
                'Tesina',
                'Promedio General',
                'Examen General de Egreso (EGEL)',
                'Memoria de Experiencia Profesional',
                'Proyecto de Investigación',
                'Curso Especial de Titulación',
                'Estudios de Posgrado',
            ]),
            'requires_advisor' => fake()->boolean(),
        ];
    }

    /**
     * Indicate that this graduation type requires a thesis advisor.
     */
    public function requiresAdvisor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'requires_advisor' => true,
        ]);
    }

    /**
     * Indicate that this graduation type does not require a thesis advisor.
     */
    public function withoutAdvisor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'requires_advisor' => false,
        ]);
    }

    /**
     * Attach a set of required documents to this graduation type via the
     * graduation_type_required_document pivot. Defaults to two new documents.
     */
    public function withRequiredDocuments(int $count = 2): static
    {
        return $this->afterCreating(function (GraduationType $graduationType) use ($count): void {
            $graduationType->requiredDocuments()->attach(
                RequiredDocument::factory()->count($count)->create()->pluck('id'),
            );
        });
    }
}
