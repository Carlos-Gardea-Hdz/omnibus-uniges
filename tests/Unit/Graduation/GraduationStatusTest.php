<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Exceptions\InvalidStatusTransitionException;
use App\Domain\Graduation\StateMachine\GraduationStateMachine;

covers(GraduationStatus::class, GraduationStateMachine::class);

it('allows only the legal sequential transitions', function (
    GraduationStatus $from,
    GraduationStatus $to,
    bool $allowed,
): void {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'pending → review' => [GraduationStatus::FormBPending, GraduationStatus::FormBReview, true],
    'review → approved' => [GraduationStatus::FormBReview, GraduationStatus::AnnexesPending, true],
    'review → rejected' => [GraduationStatus::FormBReview, GraduationStatus::FormBRejected, true],
    'rejected → review (loop)' => [GraduationStatus::FormBRejected, GraduationStatus::FormBReview, true],
    'ceremony → graduated' => [GraduationStatus::CeremonyScheduled, GraduationStatus::Graduated, true],
    'skipping a state is illegal' => [GraduationStatus::FormBPending, GraduationStatus::Graduated, false],
    'no transitions out of terminal' => [GraduationStatus::Graduated, GraduationStatus::FormBPending, false],
]);

it('throws on an illegal transition via the state machine', function (): void {
    $machine = new GraduationStateMachine;

    $machine->assertCanTransition(
        GraduationStatus::FormBPending,
        GraduationStatus::Graduated,
    );
})->throws(InvalidStatusTransitionException::class);

it('marks only the graduated state as terminal', function (): void {
    expect(GraduationStatus::Graduated->isTerminal())->toBeTrue()
        ->and(GraduationStatus::FormBPending->isTerminal())->toBeFalse();
});

it('numbers all nine steps uniquely from 1 to 9', function (): void {
    $steps = array_map(
        static fn (GraduationStatus $status): int => $status->step(),
        GraduationStatus::cases(),
    );

    expect($steps)->toEqualCanonicalizing(range(1, 9));
});
