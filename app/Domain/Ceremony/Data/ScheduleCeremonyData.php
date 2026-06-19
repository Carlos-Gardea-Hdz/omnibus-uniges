<?php

declare(strict_types=1);

namespace App\Domain\Ceremony\Data;

use App\Rules\CeremonyDate;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ceremony scheduling payload (staff-facing, jury → ceremony 7 → 8 transition).
 *
 * The `CeremonyDate` custom rule keeps the three sub-checks (future, weekday,
 * business hours) cohesive and emits one i18n message per failing facet; `Date`
 * guarantees a parseable value before the custom rule runs. `ceremony_location`
 * maps to the VARCHAR(200) column.
 *
 * Spatie Data is the single source of truth: server-side validation rules AND
 * the TypeScript type consumed by the Inertia form. FormRequests are prohibited.
 */
#[TypeScript]
final class ScheduleCeremonyData extends Data
{
    public function __construct(
        #[Required, Date, Rule(new CeremonyDate)]
        public string $ceremony_date,
        #[Required, StringType, Max(200)]
        public string $ceremony_location,
    ) {}
}
