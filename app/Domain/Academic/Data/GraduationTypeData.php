<?php

declare(strict_types=1);

namespace App\Domain\Academic\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * GraduationType catalog payload (super_admin-facing CRUD).
 *
 * Spatie Data is the single source of truth: server-side validation rules AND
 * the TypeScript type consumed by the Inertia form. FormRequests are prohibited.
 * Code uniqueness is enforced in the Actions (CreateGraduationTypeAction on
 * create, UpdateGraduationTypeAction with unique-ignore-self on update) rather
 * than via a DTO Unique attribute that would reject a row's own code on update.
 * required_document_ids drives the graduation_type_required_document pivot (synced
 * by the Create/Update Actions); each id is FK-validated per-item via the rules()
 * method (a per-element `array.*` rule, which attribute validation cannot express
 * on a list<int>).
 */
#[TypeScript]
final class GraduationTypeData extends Data
{
    /**
     * @param  array<int, int>  $required_document_ids
     */
    public function __construct(
        #[Required, Max(20)]
        public string $code,
        #[Required, Max(150)]
        public string $name,
        #[BooleanType]
        public bool $requires_advisor = false,
        public array $required_document_ids = [],
    ) {}

    /**
     * Per-item FK validation for the pivot ids — Spatie's attribute layer cannot
     * target the `*` array element, so the raw rule is declared here.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'required_document_ids' => ['array'],
            'required_document_ids.*' => ['integer', Rule::exists('required_documents', 'id')],
        ];
    }
}
