<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The 9-state graduation workflow (SPEC §3.3).
 *
 * Transitions are strict and sequential — skipping states is forbidden.
 * The only branch is FORM_B_REVIEW, which may reject (→ FORM_B_REJECTED,
 * loopable back to review) or approve (→ ANNEXES_PENDING).
 */
#[TypeScript]
enum GraduationStatus: string
{
    case FormBPending = 'form_b_pending';
    case FormBReview = 'form_b_review';
    case FormBRejected = 'form_b_rejected';
    case AnnexesPending = 'annexes_pending';
    case AnnexIiiPending = 'annex_iii_pending';
    case PaymentPending = 'payment_pending';
    case JuryAssigned = 'jury_assigned';
    case CeremonyScheduled = 'ceremony_scheduled';
    case Graduated = 'graduated';

    /** Sequence number used for ordering and progress UI (1–9). */
    public function step(): int
    {
        return match ($this) {
            self::FormBPending => 1,
            self::FormBReview => 2,
            self::FormBRejected => 3,
            self::AnnexesPending => 4,
            self::AnnexIiiPending => 5,
            self::PaymentPending => 6,
            self::JuryAssigned => 7,
            self::CeremonyScheduled => 8,
            self::Graduated => 9,
        };
    }

    /** i18n key consumed by the bilingual frontend (status.*). */
    public function labelKey(): string
    {
        return 'status.'.$this->value;
    }

    /** Semantic colour token for badges (maps to CSS theme tokens). */
    public function color(): string
    {
        return match ($this) {
            self::FormBRejected => 'danger',
            self::Graduated => 'success',
            self::FormBPending, self::AnnexesPending,
            self::AnnexIiiPending, self::PaymentPending => 'warning',
            default => 'primary',
        };
    }

    /**
     * The states this state is allowed to transition into.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::FormBPending => [self::FormBReview],
            self::FormBReview => [self::FormBRejected, self::AnnexesPending],
            self::FormBRejected => [self::FormBReview],
            self::AnnexesPending => [self::AnnexIiiPending],
            self::AnnexIiiPending => [self::PaymentPending],
            self::PaymentPending => [self::JuryAssigned],
            self::JuryAssigned => [self::CeremonyScheduled],
            self::CeremonyScheduled => [self::Graduated],
            self::Graduated => [],
        };
    }

    /** Guard for the state machine — never allow skipping states. */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Graduated;
    }
}
