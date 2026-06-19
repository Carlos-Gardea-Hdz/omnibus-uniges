<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Data;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Formato B submission payload (the student-facing graduation intake form).
 *
 * Spatie Data is the single source of truth: it carries the server-side
 * validation rules AND generates the TypeScript type consumed by the
 * Inertia form. FormRequests / $request->validate() are prohibited.
 */
#[TypeScript]
final class SubmitFormBData extends Data
{
    public function __construct(
        #[Required, Regex('/^[0-9]{8,12}$/')]
        public string $control_number,
        #[Required, Max(100)]
        public string $first_name,
        #[Required, Max(100)]
        public string $last_name,
        #[Required, In('male', 'female', 'other')]
        public string $gender,
        #[Required, Numeric, Min(70), Max(100)]
        public float $gpa,
        #[Required, Date]
        public string $enrollment_date,
        #[Required, IntegerType, Exists('programs', 'id')]
        public int $program_id,
        #[Required, IntegerType, Exists('graduation_types', 'id')]
        public int $graduation_type_id,
        #[Required, IntegerType, Exists('study_plans', 'id')]
        public int $study_plan_id,
        #[Max(100)]
        public ?string $mother_last_name = null,
        #[Max(300)]
        public ?string $thesis_title = null,
        public ?string $thesis_abstract = null,
        #[Exists('professors', 'id')]
        public ?int $advisor_id = null,
        #[Max(15)]
        public ?string $phone = null,
        #[Max(15)]
        public ?string $mobile = null,
        public ?int $age = null,
        #[Max(125)]
        public ?string $address_street = null,
        #[Max(100)]
        public ?string $address_neighborhood = null,
        #[Max(11)]
        public ?string $address_ext_number = null,
        #[Max(11)]
        public ?string $address_int_number = null,
        public ?int $address_postal_code = null,
    ) {}
}
