<?php

declare(strict_types=1);

namespace App\Domain\Jury\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The four roles a professor can hold on a graduation jury (SPEC §003).
 *
 * president / secretary / vocal → the three mandatory, distinct members.
 * substitute → optional stand-in, distinct from the three when present.
 */
#[TypeScript]
enum JuryRole: string
{
    case President = 'president';
    case Secretary = 'secretary';
    case Vocal = 'vocal';
    case Substitute = 'substitute';

    /** i18n key consumed by the bilingual frontend (jury_role.*). */
    public function labelKey(): string
    {
        return 'jury_role.'.$this->value;
    }

    /** Semantic colour token for badges (maps to CSS theme tokens). */
    public function color(): string
    {
        return match ($this) {
            self::Substitute => 'warning',
            default => 'primary',
        };
    }
}
