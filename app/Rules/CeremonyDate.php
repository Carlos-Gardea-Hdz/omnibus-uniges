<?php

declare(strict_types=1);

namespace App\Rules;

use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Validates a ceremony datetime against SPEC §3.6 CERE-01: it must be in the
 * future, fall on a weekday (Mon-Fri), and sit inside business hours
 * (08:00–17:59; the window is `>= 08:00` and `< 18:00`, so 08:00 is the first
 * valid slot and 18:00 the first invalid one).
 *
 * `App\Rules` is HTTP-validation infrastructure (NOT `App\Domain`), so it is
 * outside the Domain↛Http architecture rule and may depend on Carbon. Each
 * failing facet emits one i18n message key so the frontend can localise it.
 */
final class CeremonyDate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.ceremony.invalid');

            return;
        }

        try {
            $date = Carbon::parse($value);
        } catch (InvalidFormatException) {
            $fail('validation.ceremony.invalid');

            return;
        }

        if (! $date->isFuture()) {
            $fail('validation.ceremony.not_future');
        }

        if ($date->isWeekend()) {
            $fail('validation.ceremony.not_weekday');
        }

        if ($date->hour < 8 || $date->hour >= 18) {
            $fail('validation.ceremony.out_of_hours');
        }
    }
}
