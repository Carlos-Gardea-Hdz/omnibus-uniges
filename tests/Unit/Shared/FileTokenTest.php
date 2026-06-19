<?php

declare(strict_types=1);

use App\Domain\Shared\ValueObjects\FileToken;
use Illuminate\Support\Str;

covers(FileToken::class);

/*
 * FileToken is a validate-in-constructor Value Object wrapping a UUID that
 * opaquely names a stored upload (CONTRACT §4). If it was built, it is valid:
 * the constructor rejects anything that is not a UUID, and generate() mints a
 * fresh sortable UUIDv7.
 */

it('wraps a valid UUID string', function (): void {
    $uuid = (string) Str::uuid7();

    expect((new FileToken($uuid))->value)->toBe($uuid);
});

it('rejects a non-UUID value', function (string $invalid): void {
    new FileToken($invalid);
})->with([
    'plain text' => 'not-a-uuid',
    'too short' => '1234',
    'empty' => '',
    'almost a uuid' => '00000000-0000-0000-0000-00000000000',
])->throws(InvalidArgumentException::class);

it('generates a fresh, valid UUID each time', function (): void {
    $first = FileToken::generate();
    $second = FileToken::generate();

    expect(Str::isUuid($first->value))->toBeTrue()
        ->and(Str::isUuid($second->value))->toBeTrue()
        ->and($first->value)->not->toBe($second->value);
});

it('stringifies to its underlying UUID', function (): void {
    $token = FileToken::generate();

    expect((string) $token)->toBe($token->value);
});
