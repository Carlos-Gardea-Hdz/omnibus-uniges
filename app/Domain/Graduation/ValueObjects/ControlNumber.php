<?php

declare(strict_types=1);

namespace App\Domain\Graduation\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Student control number (número de control). 8–12 digits.
 * Validate-in-constructor: if it exists, it is valid (SPEC §5.5).
 */
final readonly class ControlNumber implements Stringable
{
    public function __construct(
        public string $value,
    ) {
        if (preg_match('/^\d{8,12}$/', $this->value) !== 1) {
            throw new InvalidArgumentException(
                "Invalid control number: must be 8–12 digits, got [{$this->value}]."
            );
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
