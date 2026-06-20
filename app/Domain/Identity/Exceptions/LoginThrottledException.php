<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use DomainException;

/**
 * Raised when an email has hit the progressive login block (SPEC §10.3,
 * AUTH-01) and is still inside its cooldown window. Carries the remaining
 * `secondsRemaining` so the HTTP layer can render the "Try again in N seconds"
 * message without re-deriving it from the ledger.
 */
final class LoginThrottledException extends DomainException
{
    private function __construct(
        public readonly int $secondsRemaining,
    ) {
        parent::__construct("Login throttled: retry allowed in {$secondsRemaining} second(s).");
    }

    public static function retryIn(int $secondsRemaining): self
    {
        return new self(max(1, $secondsRemaining));
    }
}
