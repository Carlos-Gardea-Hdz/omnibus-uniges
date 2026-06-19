<?php

declare(strict_types=1);

use App\Domain\Shared\ValueObjects\Address;

covers(Address::class);

/*
 * Address is a validate-in-constructor Value Object: every field is optional,
 * but a supplied postal code must fall in the 5-digit range (10000–99999),
 * mirroring the students table CHECK constraint.
 */

it('builds a valid address with all fields populated', function (): void {
    $address = new Address(
        street: 'Av. Tecnológico',
        neighborhood: 'Centro',
        extNumber: '1500',
        intNumber: 'A',
        postalCode: 34080,
    );

    expect($address->street)->toBe('Av. Tecnológico')
        ->and($address->neighborhood)->toBe('Centro')
        ->and($address->extNumber)->toBe('1500')
        ->and($address->intNumber)->toBe('A')
        ->and($address->postalCode)->toBe(34080);
});

it('builds an empty address — every field is optional', function (): void {
    $address = new Address;

    expect($address->street)->toBeNull()
        ->and($address->neighborhood)->toBeNull()
        ->and($address->extNumber)->toBeNull()
        ->and($address->intNumber)->toBeNull()
        ->and($address->postalCode)->toBeNull();
});

it('accepts the boundary postal codes', function (int $postalCode): void {
    expect((new Address(postalCode: $postalCode))->postalCode)->toBe($postalCode);
})->with(['lower bound' => 10000, 'upper bound' => 99999]);

it('rejects a postal code outside the 5-digit range', function (int $invalid): void {
    new Address(postalCode: $invalid);
})->with([
    'below range' => 9999,
    'above range' => 100000,
    'zero' => 0,
])->throws(InvalidArgumentException::class);

it('round-trips through the flat student attribute representation', function (): void {
    $attributes = [
        'address_street' => 'Calle Falsa',
        'address_neighborhood' => 'Las Lomas',
        'address_ext_number' => '123',
        'address_int_number' => null,
        'address_postal_code' => 34000,
    ];

    $address = Address::fromStudentAttributes($attributes);

    expect($address->toArray())->toBe($attributes);
});

it('rejects an out-of-range postal code when rebuilt from student attributes', function (): void {
    Address::fromStudentAttributes(['address_postal_code' => 99]);
})->throws(InvalidArgumentException::class);
