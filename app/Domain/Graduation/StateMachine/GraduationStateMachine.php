<?php

declare(strict_types=1);

namespace App\Domain\Graduation\StateMachine;

use App\Domain\Graduation\Enums\GraduationStatus;
use App\Domain\Graduation\Exceptions\InvalidStatusTransitionException;

/**
 * Pure, dependency-free guard for the 9-state graduation workflow (SPEC §5.7).
 * Domain-level enforcement: the source of truth for legal transitions.
 * Actions call assertCanTransition() inside their DB::transaction().
 */
final readonly class GraduationStateMachine
{
    public function canTransition(GraduationStatus $from, GraduationStatus $to): bool
    {
        return $from->canTransitionTo($to);
    }

    /**
     * @throws InvalidStatusTransitionException when the transition is illegal.
     */
    public function assertCanTransition(GraduationStatus $from, GraduationStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw InvalidStatusTransitionException::between($from, $to);
        }
    }
}
