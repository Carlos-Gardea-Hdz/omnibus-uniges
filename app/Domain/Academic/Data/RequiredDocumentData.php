<?php

declare(strict_types=1);

namespace App\Domain\Academic\Data;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * RequiredDocument catalog payload (super_admin-facing CRUD).
 *
 * Spatie Data is the single source of truth: server-side validation rules AND
 * the TypeScript type consumed by the Inertia form. FormRequests are prohibited.
 * RequiredDocument has NO unique column and NO SoftDeletes (its migration omits
 * both), so this DTO carries no Unique rule and the Update Action skips the
 * unique-ignore-self pre-check. allowed_mimes is a CSV string; max_size_kb is
 * bounded (1 KB .. 100 MB) and explicit.
 */
#[TypeScript]
final class RequiredDocumentData extends Data
{
    public function __construct(
        #[Required, Max(150)]
        public string $name,
        #[Required, Max(255)]
        public string $allowed_mimes,
        #[Required, IntegerType, Min(1), Max(102400)]
        public int $max_size_kb,
        #[Max(1000)]
        public ?string $description = null,
    ) {}
}
