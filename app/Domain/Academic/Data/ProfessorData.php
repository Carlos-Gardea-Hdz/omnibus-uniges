<?php

declare(strict_types=1);

namespace App\Domain\Academic\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Professor catalog payload (super_admin-facing CRUD).
 *
 * Spatie Data is the single source of truth: server-side validation rules AND
 * the TypeScript type consumed by the Inertia form. FormRequests are prohibited.
 * Email uniqueness is enforced in the Actions (CreateProfessorAction on create,
 * UpdateProfessorAction with unique-ignore-self on update) rather than via a DTO
 * Unique attribute that would reject a row's own email on update. mother_last_name
 * is optional.
 */
#[TypeScript]
final class ProfessorData extends Data
{
    public function __construct(
        #[Required, Max(100)]
        public string $first_name,
        #[Required, Max(100)]
        public string $last_name,
        #[Required, Email, Max(255)]
        public string $email,
        #[Max(100)]
        public ?string $mother_last_name = null,
    ) {}
}
