<?php

declare(strict_types=1);

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Exceptions\InvalidStatusTransitionException;
use App\Domain\Graduation\StateMachine\GraduationStateMachine;

covers(GraduationStateMachine::class, GraduationStatus::class);

/*
 * The state machine is the domain's source of truth for legal transitions
 * (SPEC §5.7). These tests pin down every legal edge of the 9-state workflow
 * and prove that any skipped edge is rejected loudly.
 */

/**
 * Every legal edge of the workflow, derived from the enum's own
 * allowedTransitions(). The only branch is FormBReview (approve | reject) and
 * the only loop is FormBRejected → FormBReview.
 *
 * @return list<array{GraduationStatus, GraduationStatus}>
 */
function legalEdges(): array
{
    $edges = [];

    foreach (GraduationStatus::cases() as $from) {
        foreach ($from->allowedTransitions() as $to) {
            $edges[] = [$from, $to];
        }
    }

    return $edges;
}

it('permits every legal edge of the 9-state workflow', function (
    GraduationStatus $from,
    GraduationStatus $to,
): void {
    $machine = new GraduationStateMachine;

    expect($machine->canTransition($from, $to))->toBeTrue();

    // assertCanTransition must NOT throw for a legal edge.
    $machine->assertCanTransition($from, $to);
})->with(legalEdges());

it('lists exactly the expected legal edges', function (): void {
    expect(legalEdges())->toHaveCount(9);
});

it('throws InvalidStatusTransitionException when a state is skipped', function (
    GraduationStatus $from,
    GraduationStatus $to,
): void {
    $machine = new GraduationStateMachine;

    expect($machine->canTransition($from, $to))->toBeFalse();

    $machine->assertCanTransition($from, $to);
})->with([
    'pending → graduated (skips everything)' => [
        GraduationStatus::FormBPending,
        GraduationStatus::Graduated,
    ],
    'review → ceremony (skips approval chain)' => [
        GraduationStatus::FormBReview,
        GraduationStatus::CeremonyScheduled,
    ],
    'annexes → payment (skips annex III)' => [
        GraduationStatus::AnnexesPending,
        GraduationStatus::PaymentPending,
    ],
    'out of terminal graduated' => [
        GraduationStatus::Graduated,
        GraduationStatus::FormBPending,
    ],
    'self-loop is illegal' => [
        GraduationStatus::PaymentPending,
        GraduationStatus::PaymentPending,
    ],
])->throws(InvalidStatusTransitionException::class);

it('reports the offending states in the thrown message', function (): void {
    $machine = new GraduationStateMachine;

    expect(fn (): mixed => $machine->assertCanTransition(
        GraduationStatus::FormBPending,
        GraduationStatus::Graduated,
    ))->toThrow(
        InvalidStatusTransitionException::class,
        'form_b_pending → graduated',
    );
});

/*
 * Slice 003 (jury) edges of the same machine: the only legal step out of
 * PaymentPending is JuryAssigned (the 6 → 7 transition the AssignJuryAction
 * drives). Reaching JuryAssigned from any earlier state, or looping on it, must
 * be rejected loudly.
 */

it('permits the 6 → 7 PaymentPending → JuryAssigned transition', function (): void {
    $machine = new GraduationStateMachine;

    expect($machine->canTransition(
        GraduationStatus::PaymentPending,
        GraduationStatus::JuryAssigned,
    ))->toBeTrue();

    // Must not throw on the legal jury edge.
    $machine->assertCanTransition(
        GraduationStatus::PaymentPending,
        GraduationStatus::JuryAssigned,
    );
});

it('rejects jumping to JuryAssigned before payment (AnnexIiiPending → JuryAssigned)', function (): void {
    $machine = new GraduationStateMachine;

    expect($machine->canTransition(
        GraduationStatus::AnnexIiiPending,
        GraduationStatus::JuryAssigned,
    ))->toBeFalse();

    $machine->assertCanTransition(
        GraduationStatus::AnnexIiiPending,
        GraduationStatus::JuryAssigned,
    );
})->throws(InvalidStatusTransitionException::class);

it('rejects re-assigning a jury (JuryAssigned → JuryAssigned self-loop)', function (): void {
    $machine = new GraduationStateMachine;

    expect($machine->canTransition(
        GraduationStatus::JuryAssigned,
        GraduationStatus::JuryAssigned,
    ))->toBeFalse();

    $machine->assertCanTransition(
        GraduationStatus::JuryAssigned,
        GraduationStatus::JuryAssigned,
    );
})->throws(InvalidStatusTransitionException::class);
