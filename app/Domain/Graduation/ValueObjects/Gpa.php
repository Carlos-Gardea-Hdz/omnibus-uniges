<?php

declare(strict_types=1);

namespace App\Domain\Graduation\ValueObjects;

use InvalidArgumentException;

/**
 * Grade point average on the institutional 70–100 scale (SPEC §3.1 AUTH-03).
 * Stored as an integer of hundredths to avoid float drift (e.g. 8550 = 85.50).
 */
final readonly class Gpa
{
    private const int MIN_HUNDREDTHS = 7000;

    private const int MAX_HUNDREDTHS = 10000;

    public function __construct(
        public int $hundredths,
    ) {
        if ($this->hundredths < self::MIN_HUNDREDTHS || $this->hundredths > self::MAX_HUNDREDTHS) {
            throw new InvalidArgumentException(
                "Invalid GPA: must be between 70.00 and 100.00, got [{$this->toDecimal()}]."
            );
        }
    }

    public static function fromDecimal(float $value): self
    {
        return new self((int) round($value * 100));
    }

    public function toDecimal(): float
    {
        return $this->hundredths / 100;
    }
}
