<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academic\Models\RequiredDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequiredDocument>
 */
class RequiredDocumentFactory extends Factory
{
    protected $model = RequiredDocument::class;

    /**
     * Fictional demo data only — no real document names or records.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Name is not a unique DB column; don't exhaust a tiny unique() pool.
            'name' => fake()->randomElement([
                'Acta de Nacimiento',
                'Certificado de Estudios',
                'Comprobante de Pago',
                'Carta de Liberación',
                'Constancia de No Adeudo',
                'Fotografías Tamaño Título',
                'Anexo III Firmado',
            ]),
            'description' => fake()->optional()->sentence(8),
            'allowed_mimes' => 'application/pdf,image/jpeg,image/png',
            'max_size_kb' => 10240,
        ];
    }

    /** Only PDF files are accepted for this document. */
    public function pdfOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'allowed_mimes' => 'application/pdf',
        ]);
    }
}
