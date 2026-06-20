<?php

declare(strict_types=1);

namespace App\Domain\Academic\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Department catalog payload (super_admin-facing CRUD).
 *
 * Spatie Data is the single source of truth: server-side validation rules AND
 * the TypeScript type consumed by the Inertia form. FormRequests are prohibited.
 * Code uniqueness is enforced in the Actions, not via a DTO Unique attribute
 * (which would reject a row's OWN code on update): CreateDepartmentAction rejects
 * a duplicate on create, UpdateDepartmentAction does the unique-ignore-self
 * (whereKeyNot()) pre-check — mirroring the slice-001 control_number pattern. This
 * keeps one route-agnostic DTO serving both store and update.
 */
#[TypeScript]
final class DepartmentData extends Data
{
    public function __construct(
        #[Required, Max(20)]
        public string $code,
        #[Required, Max(150)]
        public string $name,
    ) {}
}
