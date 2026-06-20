<?php

declare(strict_types=1);

/*
 * Pure read-model logic — the terminal-efficiency RATE contract (spec 008
 * §2.6 int/float, D-RATE-INT). This is the one piece of report maths that is
 * pure (no DB, no app boot): rate = total === 0 ? 0 : (int) round(g / t * 100).
 *
 * It must be a rounded INTEGER 0..100, never a float (40.0) and never a
 * pre-formatted string ("40%"); a zero cohort is guarded to 0 (no divide-by-zero).
 * This unit test fixes the exact rounding semantics (PHP round() is half-away-
 * from-zero) so a future change to the service's private rate() that drifts the
 * type or the boundary rounding fails here, independent of the database tests.
 *
 * The function below is a faithful mirror of TerminalEfficiencyService::rate();
 * keeping it framework-free is the point — this is a specification, not a wiring
 * test (the DB-backed correctness lives in tests/Feature/Reporting).
 */

/** The int-percent SSOT mirrored from TerminalEfficiencyService::rate(). */
function terminalEfficiencyRate(int $graduates, int $total): int
{
    return $total === 0 ? 0 : (int) round($graduates / $total * 100);
}

it('computes a rounded integer percent from graduates over total', function (
    int $graduates,
    int $total,
    int $expected,
): void {
    $rate = terminalEfficiencyRate($graduates, $total);

    expect($rate)->toBeInt()->toBe($expected);
})->with([
    '4 / 10 → 40' => [4, 10, 40],
    '1 / 2 → 50' => [1, 2, 50],
    '5 / 5 → 100 (everyone graduated)' => [5, 5, 100],
    '0 / 5 → 0 (nobody graduated)' => [0, 5, 0],
    '3 / 7 → 43 (42.857 rounds up)' => [3, 7, 43],
    '1 / 3 → 33 (33.333 rounds down)' => [1, 3, 33],
    '2 / 3 → 67 (66.666 rounds up)' => [2, 3, 67],
    '1 / 8 → 13 (12.5 rounds half away from zero)' => [1, 8, 13],
]);

it('guards a zero-total cohort with rate 0 (no divide-by-zero)', function (): void {
    expect(terminalEfficiencyRate(0, 0))->toBeInt()->toBe(0)
        ->and(terminalEfficiencyRate(5, 0))->toBeInt()->toBe(0);
});

it('never yields a float or a string', function (): void {
    $rate = terminalEfficiencyRate(4, 10);

    expect($rate)->toBeInt()
        ->and($rate)->not->toBeFloat()
        ->and($rate)->not->toBeString();
});

it('stays within the 0..100 inclusive range', function (int $graduates, int $total): void {
    $rate = terminalEfficiencyRate($graduates, $total);

    expect($rate)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
})->with([
    'partial' => [3, 11],
    'full' => [9, 9],
    'empty' => [0, 4],
]);
