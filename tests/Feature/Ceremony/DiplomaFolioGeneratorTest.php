<?php

declare(strict_types=1);

use App\Domain\Academic\Models\Program;
use App\Domain\Ceremony\Actions\MarkAsGraduatedAction;
use App\Domain\Ceremony\Models\DiplomaFolioSequence;
use App\Domain\Ceremony\Services\DiplomaFolioGenerator;
use App\Domain\Graduation\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

covers(DiplomaFolioGenerator::class);

/*
 * The diploma folio is {YEAR}-{PROGRAM_CODE}-{SEQ}, unique per program per year,
 * with a 3-digit zero-padded SEQ that MUST be race-safe (CONTRACT §4, SPEC
 * scenario 9 / GRAD-03). We drive generation through MarkAsGraduatedAction (the
 * sole caller — the lock lives inside its transaction) so the assertions hold
 * regardless of whether generate() returns a VO or a raw string: the persisted
 * Student::$diploma_folio is the contract. DB-backed → tests/Feature/.
 */

beforeEach(function (): void {
    // No broadcasting side effects; we assert only on the minted folios.
    Event::fake();
});

/** Graduate a CeremonyScheduled student whose ceremony has passed, return the folio. */
function mintFolioFor(Student $student): string
{
    app(MarkAsGraduatedAction::class)->handle($student);

    /** @var string $folio */
    $folio = $student->fresh()->diploma_folio;

    return $folio;
}

it('mints a folio in the {YEAR}-{CODE}-{SEQ} format with the program code', function (): void {
    $program = Program::factory()->create(['code' => 'ISC01']);
    $student = Student::factory()->ceremonyPassed()->create(['program_id' => $program->id]);

    $folio = mintFolioFor($student);

    $year = (string) now()->year;

    expect($folio)->toBe("{$year}-ISC01-001")
        ->and($folio)->toMatch('/^\d{4}-[A-Z0-9]+-\d{3}$/');
});

it('increments sequentially for the same program and year (001 → 002 → 003)', function (): void {
    $program = Program::factory()->create(['code' => 'IND02']);
    $year = (string) now()->year;

    $folios = collect(range(1, 3))->map(function () use ($program): string {
        $student = Student::factory()->ceremonyPassed()->create(['program_id' => $program->id]);

        return mintFolioFor($student);
    });

    expect($folios->all())->toBe([
        "{$year}-IND02-001",
        "{$year}-IND02-002",
        "{$year}-IND02-003",
    ]);
});

it('restarts the sequence at 001 for a different program', function (): void {
    $first = Program::factory()->create(['code' => 'AAA11']);
    $second = Program::factory()->create(['code' => 'BBB22']);
    $year = (string) now()->year;

    $studentA = Student::factory()->ceremonyPassed()->create(['program_id' => $first->id]);
    $studentB = Student::factory()->ceremonyPassed()->create(['program_id' => $second->id]);

    expect(mintFolioFor($studentA))->toBe("{$year}-AAA11-001")
        ->and(mintFolioFor($studentB))->toBe("{$year}-BBB22-001");
});

it('continues from a pre-seeded counter (sequence persists across the year)', function (): void {
    $program = Program::factory()->create(['code' => 'SEQ09']);
    $year = (int) now()->year;

    // Pre-seed the per-program-per-year counter at 9 → next folio is …-010.
    DiplomaFolioSequence::factory()
        ->withValue(9)
        ->create(['program_id' => $program->id, 'year' => $year]);

    $student = Student::factory()->ceremonyPassed()->create(['program_id' => $program->id]);

    expect(mintFolioFor($student))->toBe("{$year}-SEQ09-010");
});

// Sequential increment + per-(program,year) uniqueness. True concurrency is not
// exercised here (single connection/process); serialization under contention is
// enforced at runtime by lockForUpdate() inside MarkAsGraduatedAction's
// transaction, with the partial unique index on students.diploma_folio as a
// fail-loud backstop.
it('mints distinct, sequentially incrementing folios for same-program graduations', function (): void {
    $program = Program::factory()->create(['code' => 'RACE7']);
    $year = (string) now()->year;

    $studentA = Student::factory()->ceremonyPassed()->create(['program_id' => $program->id]);
    $studentB = Student::factory()->ceremonyPassed()->create(['program_id' => $program->id]);

    $folioA = mintFolioFor($studentA);
    $folioB = mintFolioFor($studentB);

    expect($folioA)->not->toBe($folioB)
        ->and([$folioA, $folioB])->toBe([
            "{$year}-RACE7-001",
            "{$year}-RACE7-002",
        ]);
});
