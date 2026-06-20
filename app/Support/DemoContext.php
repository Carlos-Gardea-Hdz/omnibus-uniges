<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Per-request holder for the active demo session tag (slice 006).
 *
 * Bound as a singleton in AppServiceProvider so the DemoScope global scope and
 * the DemoSessionMiddleware share one instance per request. It starts null and
 * STAYS null on real (non-demo) HTTP requests, on CLI requests, and on the
 * scheduled cleanup — which is exactly what the symmetric DemoScope relies on:
 * a null context means "scope to real rows only" (demo_session_id IS NULL).
 *
 * It is placed under App\Support (a domain-neutral location) rather than under
 * App\Domain\Identity on purpose: the Graduation and Jury models consume the
 * DemoScope, and the architecture tests forbid those domains from importing
 * App\Domain\Identity. A neutral home keeps cross-domain isolation intact.
 */
final class DemoContext
{
    private ?string $sessionId = null;

    /** Set (or clear) the active demo session tag for this request. */
    public function set(?string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    /** The active demo session tag, or null on real/CLI requests. */
    public function sessionId(): ?string
    {
        return $this->sessionId;
    }
}
