<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academic\Models\RequiredDocument;
use App\Domain\Graduation\Enums\DocumentStatus;
use App\Domain\Graduation\Models\Student;
use App\Domain\Graduation\Models\StudentDocument;
use App\Domain\Shared\ValueObjects\FileToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentDocument>
 */
class StudentDocumentFactory extends Factory
{
    protected $model = StudentDocument::class;

    /**
     * Define the model's default state — a pending slot with no file yet.
     *
     * Fictional demo data only — no real files, names or records.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'required_document_id' => RequiredDocument::factory(),
            'reviewed_by' => null,
            'file_path' => null,
            'file_token' => (string) FileToken::generate(),
            'original_filename' => null,
            'mime_type' => null,
            'file_size' => null,
            'status' => DocumentStatus::Pending,
            'rejection_reason' => null,
            'uploaded_at' => null,
            'reviewed_at' => null,
        ];
    }

    /** A freshly seeded slot, nothing uploaded. */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentStatus::Pending,
            'file_path' => null,
            'original_filename' => null,
            'mime_type' => null,
            'file_size' => null,
            'uploaded_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);
    }

    /** A file has been uploaded and is awaiting review. */
    public function uploaded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentStatus::Uploaded,
            'file_path' => 'documents/1/uploads/'.fake()->uuid().'.pdf',
            'original_filename' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(50_000, 5_000_000),
            'uploaded_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);
    }

    /** Reviewer accepted the file. */
    public function approved(): static
    {
        return $this->uploaded()->state(fn (array $attributes): array => [
            'status' => DocumentStatus::Approved,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    /** Reviewer rejected the file with a reason. */
    public function rejected(): static
    {
        return $this->uploaded()->state(fn (array $attributes): array => [
            'status' => DocumentStatus::Rejected,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
            'rejection_reason' => fake()->sentence(8),
        ]);
    }
}
