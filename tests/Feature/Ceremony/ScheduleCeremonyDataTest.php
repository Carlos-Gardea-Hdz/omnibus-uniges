<?php

declare(strict_types=1);

use App\Domain\Ceremony\Data\ScheduleCeremonyData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

covers(ScheduleCeremonyData::class);

/*
 * Validation coverage for the schedule-ceremony DTO (CONTRACT §3). The DTO is the
 * validation SSOT: ceremony_date carries #[Required, Date, Rule(new CeremonyDate)]
 * (future + weekday + business hours) and ceremony_location is #[Required,
 * StringType, Max(200)]. Because Spatie Data::validateAndCreate resolves the
 * custom rule through the container, this test lives in tests/Feature/ (a Unit
 * test calling it would throw BindingResolutionException).
 */

/** A future weekday at the given H:i, in the Y-m-d H:i shape the form posts. */
function dtoDateAt(int $hour = 10, int $minute = 0): string
{
    $date = Carbon::now()->addWeeks(2)->setTime($hour, $minute, 0);

    while ($date->isWeekend()) {
        $date->addDay();
    }

    return $date->format('Y-m-d H:i');
}

it('validates a future weekday business-hours payload', function (): void {
    $data = ScheduleCeremonyData::validateAndCreate([
        'ceremony_date' => dtoDateAt(),
        'ceremony_location' => 'Auditorio Central',
    ]);

    expect($data->ceremony_location)->toBe('Auditorio Central')
        ->and($data->ceremony_date)->toBe(dtoDateAt());
});

it('accepts the 08:00 and 17:59 business-hour boundaries', function (
    int $hour,
    int $minute,
): void {
    $data = ScheduleCeremonyData::validateAndCreate([
        'ceremony_date' => dtoDateAt($hour, $minute),
        'ceremony_location' => 'Auditorio Central',
    ]);

    expect($data->ceremony_date)->toBe(dtoDateAt($hour, $minute));
})->with([
    'lower boundary 08:00' => [8, 0],
    'upper boundary 17:59' => [17, 59],
]);

it('rejects a past ceremony date on the ceremony_date field', function (): void {
    $past = Carbon::now()->subWeek()->setTime(10, 0)->format('Y-m-d H:i');

    $errors = validationErrorsFor([
        'ceremony_date' => $past,
        'ceremony_location' => 'Auditorio Central',
    ]);

    expect($errors)->toHaveKey('ceremony_date');
});

it('rejects a weekend ceremony date on the ceremony_date field', function (): void {
    $saturday = Carbon::now()->addWeek()->next(Carbon::SATURDAY)->setTime(10, 0)->format('Y-m-d H:i');

    $errors = validationErrorsFor([
        'ceremony_date' => $saturday,
        'ceremony_location' => 'Auditorio Central',
    ]);

    expect($errors)->toHaveKey('ceremony_date');
});

it('rejects a time before 08:00 on the ceremony_date field', function (): void {
    $errors = validationErrorsFor([
        'ceremony_date' => dtoDateAt(7, 59),
        'ceremony_location' => 'Auditorio Central',
    ]);

    expect($errors)->toHaveKey('ceremony_date');
});

it('rejects a time at 18:00 on the ceremony_date field', function (): void {
    $errors = validationErrorsFor([
        'ceremony_date' => dtoDateAt(18, 0),
        'ceremony_location' => 'Auditorio Central',
    ]);

    expect($errors)->toHaveKey('ceremony_date');
});

it('rejects a missing ceremony location on the ceremony_location field', function (): void {
    $errors = validationErrorsFor([
        'ceremony_date' => dtoDateAt(),
    ]);

    expect($errors)->toHaveKey('ceremony_location');
});

it('rejects a location longer than 200 characters', function (): void {
    $errors = validationErrorsFor([
        'ceremony_date' => dtoDateAt(),
        'ceremony_location' => str_repeat('a', 201),
    ]);

    expect($errors)->toHaveKey('ceremony_location');
});

/**
 * Validate the payload and return the field-keyed error bag, or an empty array
 * when it passed.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, array<int, string>>
 */
function validationErrorsFor(array $payload): array
{
    try {
        ScheduleCeremonyData::validateAndCreate($payload);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}
