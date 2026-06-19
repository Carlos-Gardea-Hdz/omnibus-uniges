<?php

declare(strict_types=1);

use App\Rules\CeremonyDate;
use Illuminate\Support\Carbon;

covers(CeremonyDate::class);

/*
 * Pure-logic coverage for the CeremonyDate validation rule (CONTRACT §2). The
 * rule encodes the three CERE-01 sub-checks (SPEC §2538): a future date, a
 * weekday (Mon-Fri), and business hours (08:00 inclusive → 18:00 exclusive, so
 * 17:59 is the last valid minute). The rule never touches the DB, so it lives in
 * tests/Unit/. We drive it directly with a closure that records the failure key,
 * mirroring how Laravel calls a ValidationRule::validate($attr, $value, $fail).
 */

/**
 * Run the rule against a value and return the failure key it emitted, or null
 * when the value passed (the closure was never invoked).
 */
function ceremonyDateFailure(string $value): ?string
{
    $failure = null;

    (new CeremonyDate)->validate(
        'ceremony_date',
        $value,
        function (string $message) use (&$failure): void {
            $failure = $message;
        },
    );

    return $failure;
}

/** A future weekday at the given H:i, formatted as the form sends it (Y-m-d H:i). */
function futureWeekdayAt(int $hour, int $minute = 0): string
{
    $date = Carbon::now()->addWeek()->setTime($hour, $minute, 0);

    // Nudge forward onto Monday if the +1 week landed on a weekend.
    while ($date->isWeekend()) {
        $date->addDay();
    }

    return $date->format('Y-m-d H:i');
}

it('passes a future weekday inside business hours', function (): void {
    expect(ceremonyDateFailure(futureWeekdayAt(10)))->toBeNull();
});

it('rejects a past date with the not_future key', function (): void {
    // A weekday in business hours, two weeks ago — only the future check fails.
    $past = Carbon::now()->subWeeks(2)->setTime(10, 0);

    while ($past->isWeekend()) {
        $past->subDay();
    }

    expect(ceremonyDateFailure($past->format('Y-m-d H:i')))
        ->toBe('validation.ceremony.not_future');
});

it('rejects a future weekend date with the not_weekday key', function (): void {
    $saturday = Carbon::now()->addWeek()->next(Carbon::SATURDAY)->setTime(10, 0);

    expect(ceremonyDateFailure($saturday->format('Y-m-d H:i')))
        ->toBe('validation.ceremony.not_weekday');
});

it('rejects an unparseable value with the invalid key', function (): void {
    expect(ceremonyDateFailure('not-a-date-at-all'))
        ->toBe('validation.ceremony.invalid');
});

it('rejects a time before 08:00 with the out_of_hours key (07:59)', function (): void {
    expect(ceremonyDateFailure(futureWeekdayAt(7, 59)))
        ->toBe('validation.ceremony.out_of_hours');
});

it('rejects a time at or after 18:00 with the out_of_hours key (18:00)', function (): void {
    expect(ceremonyDateFailure(futureWeekdayAt(18, 0)))
        ->toBe('validation.ceremony.out_of_hours');
});

it('accepts the 08:00 lower boundary', function (): void {
    expect(ceremonyDateFailure(futureWeekdayAt(8, 0)))->toBeNull();
});

it('accepts the 17:59 upper boundary', function (): void {
    expect(ceremonyDateFailure(futureWeekdayAt(17, 59)))->toBeNull();
});
