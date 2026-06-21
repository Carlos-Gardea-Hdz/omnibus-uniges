<?php

declare(strict_types=1);

use App\Domain\Ceremony\Actions\MarkAsGraduatedAction;
use App\Domain\Ceremony\Events\StudentGraduated;
use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Events\StudentStatusChanged;
use App\Domain\Graduation\Exceptions\InvalidStatusTransitionException;
use App\Domain\Graduation\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/*
 * BLOCKER regression — the unguarded double-transition race. Every transition
 * Action now re-loads the student under a pessimistic lock (lockForUpdate) and
 * re-reads the source state from the LOCKED row before the state-machine guard.
 * That serializes two concurrent moves: the second loses the guard and fails
 * cleanly instead of double-firing StudentStatusChanged / StudentGraduated or
 * minting a second diploma folio.
 *
 * A true parallel test is impractical in-process, so this models the race with
 * two SEQUENTIAL handle() calls that share a STALE in-memory instance: the
 * second call carries status === CeremonyScheduled in memory while the DB row
 * has already advanced to Graduated. WITHOUT the lock+re-read the second call
 * would read its stale `from`, pass the guard and double-fire. WITH it, the
 * re-read sees the committed Graduated state and the guard rejects 9→9.
 */

it('serializes a double MarkAsGraduated: the second stale call is guard-rejected, no second folio, single dispatch', function (): void {
    Event::fake([StudentStatusChanged::class, StudentGraduated::class]);

    $student = Student::factory()->ceremonyPassed()->create();

    // Two handles resolved from the SAME stale CeremonyScheduled instance — the
    // worst case of two concurrent requests both reading `from = CeremonyScheduled`.
    $staleA = Student::query()->whereKey($student->getKey())->firstOrFail();
    $staleB = Student::query()->whereKey($student->getKey())->firstOrFail();

    expect($staleA->status)->toBe(GraduationStatus::CeremonyScheduled)
        ->and($staleB->status)->toBe(GraduationStatus::CeremonyScheduled);

    $action = app(MarkAsGraduatedAction::class);

    // First commits the 8→9 transition + the folio.
    $action->handle($staleA);

    $folio = $student->fresh()->diploma_folio;
    expect($folio)->not->toBeNull();

    // Second runs AFTER the first commits, on the stale instance. The lock's
    // re-read surfaces the committed Graduated state → 9→9 guard rejection.
    expect(fn (): Student => $action->handle($staleB))
        ->toThrow(InvalidStatusTransitionException::class);

    // No second folio minted; the ledger is intact.
    expect($student->fresh()->diploma_folio)->toBe($folio);

    // The graduation events fired exactly once across BOTH attempts.
    Event::assertDispatchedTimes(StudentStatusChanged::class, 1);
    Event::assertDispatchedTimes(StudentGraduated::class, 1);
});

it('the locked re-read drives the transition off the committed row, not the passed-in instance', function (): void {
    $student = Student::factory()->ceremonyPassed()->create();

    // Advance the DB row out-of-band while holding a stale CeremonyScheduled
    // handle. The Action must transition off the LOCKED (committed) state, so a
    // stale handle whose DB row is already Graduated is rejected.
    $stale = Student::query()->whereKey($student->getKey())->firstOrFail();
    app(MarkAsGraduatedAction::class)->handle($student);

    expect(fn (): Student => app(MarkAsGraduatedAction::class)->handle($stale))
        ->toThrow(InvalidStatusTransitionException::class);
});
