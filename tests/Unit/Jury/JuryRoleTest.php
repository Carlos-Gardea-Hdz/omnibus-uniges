<?php

declare(strict_types=1);

use App\Domain\Jury\Enums\JuryRole;

covers(JuryRole::class);

/*
 * JuryRole names the four seats on a graduation jury (CONTRACT §2). It is a
 * pure presentation enum — it backs no column (the roles are distinct FK
 * columns on jury_assignments), it only drives the bilingual labels and badge
 * colours. These cases pin the four string values, the i18n key namespace and
 * the semantic colour token so the React side never drifts.
 */

it('exposes exactly the four jury seats with their string values', function (): void {
    $values = array_map(
        static fn (JuryRole $role): string => $role->value,
        JuryRole::cases(),
    );

    expect($values)->toEqualCanonicalizing(['president', 'secretary', 'vocal', 'substitute']);
});

it('is a string-backed enum', function (): void {
    expect(JuryRole::President)->toBeInstanceOf(BackedEnum::class)
        ->and(JuryRole::President->value)->toBeString();
});

it('builds an i18n key under the jury_role namespace', function (
    JuryRole $role,
    string $expected,
): void {
    expect($role->labelKey())->toBe($expected);
})->with([
    'president' => [JuryRole::President, 'jury_role.president'],
    'secretary' => [JuryRole::Secretary, 'jury_role.secretary'],
    'vocal' => [JuryRole::Vocal, 'jury_role.vocal'],
    'substitute' => [JuryRole::Substitute, 'jury_role.substitute'],
]);

it('maps each seat to its semantic colour token', function (
    JuryRole $role,
    string $color,
): void {
    expect($role->color())->toBe($color);
})->with([
    'president → primary' => [JuryRole::President, 'primary'],
    'secretary → primary' => [JuryRole::Secretary, 'primary'],
    'vocal → primary' => [JuryRole::Vocal, 'primary'],
    'substitute → warning' => [JuryRole::Substitute, 'warning'],
]);
