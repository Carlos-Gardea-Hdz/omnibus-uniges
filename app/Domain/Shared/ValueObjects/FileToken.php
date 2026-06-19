<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Stringable;

/**
 * Opaque, unguessable handle for a stored student document (SPEC §6.3.13).
 *
 * Backed by a chronologically sortable UUIDv7 so file names cannot be guessed
 * or enumerated. Used both as the unique DB column and as part of the on-disk
 * path. If constructed, it is a valid UUID.
 */
final readonly class FileToken implements Stringable
{
    public function __construct(public string $value)
    {
        if (! Str::isUuid($value)) {
            throw new InvalidArgumentException("Invalid file token: [{$value}].");
        }
    }

    public static function generate(): self
    {
        return new self((string) Str::uuid7());
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
