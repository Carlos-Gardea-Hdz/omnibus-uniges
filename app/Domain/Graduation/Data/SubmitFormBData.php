<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Data;

use App\Domain\Academic\Models\GraduationType;
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
use Spatie\LaravelData\Support\Validation\ValidationContext;
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
        // thesis_abstract is a TEXT column — bound it so an out-of-range body is
        // a graceful 302 field error, never an unbounded write / 500.
        #[Max(5000)]
        public ?string $thesis_abstract = null,
        #[Exists('professors', 'id')]
        public ?int $advisor_id = null,
        #[Max(15)]
        public ?string $phone = null,
        #[Max(15)]
        public ?string $mobile = null,
        #[IntegerType, Min(15), Max(120)]
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

    /**
     * Conditionally require an advisor: a GraduationType flagged
     * `requires_advisor` cannot have its Form B submitted without one. The
     * attribute layer can only mark advisor_id unconditionally optional, so the
     * dependency on the chosen graduation_type_id is expressed here. The Exists
     * rule on advisor_id still applies (declared via the property attribute).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $context->fullPayload;
        $graduationTypeId = $payload['graduation_type_id'] ?? null;

        $requiresAdvisor = is_numeric($graduationTypeId)
            && GraduationType::query()
                ->whereKey((int) $graduationTypeId)
                ->where('requires_advisor', true)
                ->exists();

        return [
            'advisor_id' => $requiresAdvisor ? ['required'] : ['nullable'],
        ];
    }
}
