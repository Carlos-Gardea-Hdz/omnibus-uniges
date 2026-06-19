<?php

declare(strict_types=1);

namespace App\Domain\Jury\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The student's payment-reference submission (step 6, payment pending).
 *
 * Spatie Data is the single source of truth: server-side validation rules AND
 * the TypeScript type consumed by the Inertia form. FormRequests are prohibited.
 */
#[TypeScript]
final class SubmitPaymentData extends Data
{
    public function __construct(
        #[Required, StringType, Max(50)]
        public string $payment_reference,
    ) {}
}
