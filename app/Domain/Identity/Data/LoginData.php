<?php

declare(strict_types=1);

namespace App\Domain\Identity\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Login payload (SPEC §3.1 AUTH-01). Spatie Data is the single source of
 * truth: server-side validation rules AND the generated TypeScript type.
 * `$request->validate()` / FormRequests are prohibited (project law).
 */
#[TypeScript]
final class LoginData extends Data
{
    public function __construct(
        #[Email, Max(255)]
        public string $email,
        #[Min(8), Max(255)]
        public string $password,
        public bool $remember = false,
    ) {}
}
