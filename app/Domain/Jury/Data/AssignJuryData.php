<?php

declare(strict_types=1);

namespace App\Domain\Jury\Data;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Jury assignment payload (staff-facing, payment → jury transition).
 *
 * The four professors must be pairwise distinct, covered by the six pairwise
 * `different` rules below. Laravel's `different` only fails when the other field
 * is present (Arr::has) AND strictly equal, so the optional (absent/null)
 * substitute never trips a rule. `Different` is a non-repeatable attribute, so
 * the multiple per-field comparisons are expressed via the raw-rule `Rule`
 * attribute. The database CHECK constraint is the authoritative last line.
 *
 * Spatie Data is the single source of truth: server-side validation rules AND
 * the TypeScript type consumed by the Inertia form. FormRequests are prohibited.
 */
#[TypeScript]
final class AssignJuryData extends Data
{
    public function __construct(
        #[Required, IntegerType, Exists('professors', 'id'),
            Rule('different:secretary_professor_id', 'different:vocal_professor_id', 'different:substitute_professor_id')]
        public int $president_professor_id,
        #[Required, IntegerType, Exists('professors', 'id'),
            Rule('different:vocal_professor_id', 'different:substitute_professor_id')]
        public int $secretary_professor_id,
        #[Required, IntegerType, Exists('professors', 'id'),
            Rule('different:substitute_professor_id')]
        public int $vocal_professor_id,
        #[Nullable, IntegerType, Exists('professors', 'id')]
        public ?int $substitute_professor_id = null,
    ) {}
}
