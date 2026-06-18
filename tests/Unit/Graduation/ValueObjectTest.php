<?php

declare(strict_types=1);

use App\Domain\Graduation\ValueObjects\ControlNumber;
use App\Domain\Graduation\ValueObjects\Gpa;
use App\Domain\Shared\ValueObjects\Email;

covers(ControlNumber::class, Gpa::class, Email::class);

it('accepts a valid control number', function (): void {
    expect((new ControlNumber('20231234'))->value)->toBe('20231234');
});

it('rejects an invalid control number', function (string $invalid): void {
    new ControlNumber($invalid);
})->with(['too-short' => '123', 'letters' => '20A4', 'empty' => ''])
    ->throws(InvalidArgumentException::class);

it('stores GPA as integer hundredths to avoid float drift', function (): void {
    $gpa = Gpa::fromDecimal(85.5);

    expect($gpa->hundredths)->toBe(8550)
        ->and($gpa->toDecimal())->toBe(85.5);
});

it('rejects a GPA outside the 70–100 scale', function (float $invalid): void {
    Gpa::fromDecimal($invalid);
})->with(['below' => 69.99, 'above' => 100.01])
    ->throws(InvalidArgumentException::class);

it('normalizes and validates an email', function (): void {
    $email = new Email('  Student@Example.MX ');

    expect($email->value)->toBe('student@example.mx')
        ->and($email->domain())->toBe('example.mx');
});
