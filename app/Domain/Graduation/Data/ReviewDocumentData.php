<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Data;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Reviewer's rejection payload for a single uploaded document. The reason tells
 * the student what to fix before re-uploading. Spatie Data is the single source
 * of truth for both validation and the generated TypeScript type.
 */
#[TypeScript]
final class ReviewDocumentData extends Data
{
    public function __construct(
        #[Required, StringType, Min(5)]
        public string $rejection_reason,
    ) {}
}
