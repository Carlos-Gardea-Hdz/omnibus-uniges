<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Professor;
use App\Domain\Jury\Data\AssignJuryData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

covers(AssignJuryData::class);

/*
 * Unit coverage for the jury-assignment DTO's validation rules (CONTRACT §7).
 * The four-distinct invariant is expressed as pairwise `Different` attributes
 * (all 6 pairs). A subtlety the architect flagged: Laravel's `different` rule
 * passes when the comparand field is ABSENT — so the optional substitute never
 * trips the rule when omitted. These tests pin that behaviour, plus the
 * `Exists('professors','id')` reference, against real PostgreSQL rows
 * (Exists hits the DB, so RefreshDatabase is required).
 */

/**
 * Four distinct, persisted professors to reference by id.
 *
 * @return list<int>
 */
function fourProfessorIds(): array
{
    /** @var list<int> $ids */
    $ids = array_values(
        Professor::factory()->count(4)->create()->pluck('id')->all(),
    );

    return $ids;
}

it('validates a payload with four distinct professors (substitute present)', function (): void {
    [$president, $secretary, $vocal, $substitute] = fourProfessorIds();

    $data = AssignJuryData::validateAndCreate([
        'president_professor_id' => $president,
        'secretary_professor_id' => $secretary,
        'vocal_professor_id' => $vocal,
        'substitute_professor_id' => $substitute,
    ]);

    expect($data->president_professor_id)->toBe($president)
        ->and($data->secretary_professor_id)->toBe($secretary)
        ->and($data->vocal_professor_id)->toBe($vocal)
        ->and($data->substitute_professor_id)->toBe($substitute);
});

it('validates a payload with three distinct professors and a null substitute', function (): void {
    [$president, $secretary, $vocal] = fourProfessorIds();

    $data = AssignJuryData::validateAndCreate([
        'president_professor_id' => $president,
        'secretary_professor_id' => $secretary,
        'vocal_professor_id' => $vocal,
        'substitute_professor_id' => null,
    ]);

    expect($data->substitute_professor_id)->toBeNull();
});

it('passes the different rule when the substitute is omitted entirely', function (): void {
    [$president, $secretary, $vocal] = fourProfessorIds();

    // No substitute key at all: `different` must not trip on an absent comparand.
    $data = AssignJuryData::validateAndCreate([
        'president_professor_id' => $president,
        'secretary_professor_id' => $secretary,
        'vocal_professor_id' => $vocal,
    ]);

    expect($data->substitute_professor_id)->toBeNull()
        ->and($data->president_professor_id)->toBe($president);
});

it('rejects a payload where two core professors collide', function (
    string $duplicateField,
): void {
    [$a, $b, $c] = fourProfessorIds();

    $payload = [
        'president_professor_id' => $a,
        'secretary_professor_id' => $b,
        'vocal_professor_id' => $c,
        'substitute_professor_id' => null,
    ];
    // Force the chosen field to equal the president, breaking the distinctness.
    $payload[$duplicateField] = $a;

    expect(fn (): AssignJuryData => AssignJuryData::validateAndCreate($payload))
        ->toThrow(ValidationException::class);
})->with([
    'secretary == president' => ['secretary_professor_id'],
    'vocal == president' => ['vocal_professor_id'],
]);

it('rejects a substitute that duplicates a core professor', function (): void {
    [$president, $secretary, $vocal] = fourProfessorIds();

    expect(fn (): AssignJuryData => AssignJuryData::validateAndCreate([
        'president_professor_id' => $president,
        'secretary_professor_id' => $secretary,
        'vocal_professor_id' => $vocal,
        'substitute_professor_id' => $vocal,
    ]))->toThrow(ValidationException::class);
});

it('rejects a non-existent professor id', function (): void {
    [$president, $secretary] = fourProfessorIds();

    expect(fn (): AssignJuryData => AssignJuryData::validateAndCreate([
        'president_professor_id' => $president,
        'secretary_professor_id' => $secretary,
        'vocal_professor_id' => 999_999,
        'substitute_professor_id' => null,
    ]))->toThrow(ValidationException::class);
});

it('rejects a missing required professor id', function (): void {
    [$president, $secretary] = fourProfessorIds();

    expect(fn (): AssignJuryData => AssignJuryData::validateAndCreate([
        'president_professor_id' => $president,
        'secretary_professor_id' => $secretary,
        // vocal omitted — Required must fire.
        'substitute_professor_id' => null,
    ]))->toThrow(ValidationException::class);
});

it('surfaces the president field key on the secretary collision so the form can bind the error', function (): void {
    [$president, , $vocal] = fourProfessorIds();

    // The president carries the `different:secretary_professor_id` rule, so a
    // secretary == president collision binds the error to president_professor_id,
    // a target the React form can highlight.
    $errors = [];

    try {
        AssignJuryData::validateAndCreate([
            'president_professor_id' => $president,
            'secretary_professor_id' => $president,
            'vocal_professor_id' => $vocal,
            'substitute_professor_id' => null,
        ]);
    } catch (ValidationException $exception) {
        $errors = $exception->errors();
    }

    expect($errors)->toHaveKey('president_professor_id');
});
