<?php

declare(strict_types=1);

namespace App\Domain\Academic\Data;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Program catalog payload (super_admin-facing CRUD).
 *
 * Spatie Data is the single source of truth: server-side validation rules AND
 * the TypeScript type consumed by the Inertia form. FormRequests are prohibited.
 * Code uniqueness is enforced in the Actions (CreateProgramAction on create,
 * UpdateProgramAction with unique-ignore-self on update) rather than via a DTO
 * Unique attribute that would reject a row's own code on update. department_id is
 * FK-validated via Exists.
 */
#[TypeScript]
final class ProgramData extends Data
{
    public function __construct(
        #[Required, Max(20)]
        public string $code,
        #[Required, Max(150)]
        public string $name,
        #[Required, IntegerType, Exists('departments', 'id')]
        public int $department_id,
    ) {}
}
