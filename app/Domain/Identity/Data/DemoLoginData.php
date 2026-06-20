<?php

declare(strict_types=1);

namespace App\Domain\Identity\Data;

use App\Domain\Identity\Enums\DemoPreset;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Demo-login payload (SPEC §13.1, AUTH-05). Spatie Data is the single source of
 * truth: it casts the incoming `preset` string to the DemoPreset enum and
 * validates it against the enum's cases. An out-of-set value yields a web
 * validation error keyed `preset` (302 + session errors, never a 422), so the
 * chooser can never provision an unknown persona.
 */
#[TypeScript]
final class DemoLoginData extends Data
{
    public function __construct(
        public DemoPreset $preset,
    ) {}
}
