<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * Postal address used across the program (student residence, etc.).
 * Validate-in-constructor: if it exists, it is valid. Every field is
 * optional, but a supplied postal code must fall in the 5-digit range
 * (10000–99999), mirroring the DB CHECK constraint on students.
 */
final readonly class Address
{
    private const int MIN_POSTAL_CODE = 10000;

    private const int MAX_POSTAL_CODE = 99999;

    public function __construct(
        public ?string $street = null,
        public ?string $neighborhood = null,
        public ?string $extNumber = null,
        public ?string $intNumber = null,
        public ?int $postalCode = null,
    ) {
        if ($this->postalCode !== null
            && ($this->postalCode < self::MIN_POSTAL_CODE || $this->postalCode > self::MAX_POSTAL_CODE)
        ) {
            throw new InvalidArgumentException(
                "Invalid postal code: must be between 10000 and 99999, got [{$this->postalCode}]."
            );
        }
    }

    /**
     * Rebuild from a student's flat address columns.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function fromStudentAttributes(array $attributes): self
    {
        $postalCode = $attributes['address_postal_code'] ?? null;

        return new self(
            street: self::nullableString($attributes['address_street'] ?? null),
            neighborhood: self::nullableString($attributes['address_neighborhood'] ?? null),
            extNumber: self::nullableString($attributes['address_ext_number'] ?? null),
            intNumber: self::nullableString($attributes['address_int_number'] ?? null),
            postalCode: is_numeric($postalCode) ? (int) $postalCode : null,
        );
    }

    /**
     * Flat representation matching the students table columns.
     *
     * @return array{
     *     address_street: ?string,
     *     address_neighborhood: ?string,
     *     address_ext_number: ?string,
     *     address_int_number: ?string,
     *     address_postal_code: ?int
     * }
     */
    public function toArray(): array
    {
        return [
            'address_street' => $this->street,
            'address_neighborhood' => $this->neighborhood,
            'address_ext_number' => $this->extNumber,
            'address_int_number' => $this->intNumber,
            'address_postal_code' => $this->postalCode,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
