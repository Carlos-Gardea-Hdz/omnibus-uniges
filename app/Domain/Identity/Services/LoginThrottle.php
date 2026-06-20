<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Exceptions\LoginThrottledException;
use App\Domain\Identity\Models\LoginAttempt;
use Illuminate\Support\Carbon;

/**
 * Persistent, per-email progressive login throttle (SPEC §10.3, AUTH-01).
 *
 * The algorithm, verbatim from the spec:
 *   - assertNotBlocked(): if failed_attempts >= MAX_ATTEMPTS, the cooldown is
 *     `60s × times_blocked` capped at 15 min; while (now − updated_at) is inside
 *     it, reject with the remaining seconds.
 *   - recordFailure(): increment failed_attempts; once it reaches the threshold,
 *     increment times_blocked (escalating the next cooldown).
 *   - clear(): reset failed_attempts to 0 on success; times_blocked is kept so
 *     repeat offenders escalate faster on their next streak.
 *
 * Email is matched case-insensitively (login is case-insensitive) and the ledger
 * row is created lazily on the first failure — a clean email has no row.
 */
final class LoginThrottle
{
    /** Consecutive failures before a block kicks in. */
    public const int MAX_ATTEMPTS = 5;

    /** Cooldown unit, multiplied by `times_blocked`. */
    public const int COOLDOWN_STEP_SECONDS = 60;

    /** Hard ceiling on any single cooldown (15 minutes). */
    public const int MAX_COOLDOWN_SECONDS = 900;

    /**
     * Reject the attempt if the email is currently inside its cooldown window.
     *
     * @throws LoginThrottledException
     */
    public function assertNotBlocked(string $email): void
    {
        $attempt = $this->find($email);

        if ($attempt === null || $attempt->failed_attempts < self::MAX_ATTEMPTS) {
            return;
        }

        $remaining = $this->secondsRemaining($attempt);

        if ($remaining > 0) {
            throw LoginThrottledException::retryIn($remaining);
        }
    }

    /**
     * Record a failed login: bump the consecutive-failure counter and, when it
     * crosses the threshold, escalate `times_blocked` for a longer next cooldown.
     */
    public function recordFailure(string $email): void
    {
        $attempt = LoginAttempt::query()->firstOrNew(
            ['email' => $this->normalize($email)],
            ['failed_attempts' => 0, 'times_blocked' => 0],
        );

        $attempt->failed_attempts++;

        if ($attempt->failed_attempts >= self::MAX_ATTEMPTS) {
            $attempt->times_blocked++;
        }

        $attempt->save();
    }

    /**
     * Clear the consecutive-failure counter after a successful login. The
     * `times_blocked` escalation counter is intentionally preserved.
     */
    public function clear(string $email): void
    {
        $attempt = $this->find($email);

        if ($attempt === null || $attempt->failed_attempts === 0) {
            return;
        }

        $attempt->failed_attempts = 0;
        $attempt->save();
    }

    /**
     * Seconds left in the current cooldown window (0 if it has elapsed).
     */
    private function secondsRemaining(LoginAttempt $attempt): int
    {
        $cooldown = min(
            self::COOLDOWN_STEP_SECONDS * $attempt->times_blocked,
            self::MAX_COOLDOWN_SECONDS,
        );

        $reference = $attempt->updated_at ?? Carbon::now();
        $elapsed = $reference->diffInSeconds(Carbon::now(), absolute: true);

        return (int) max(0, $cooldown - $elapsed);
    }

    private function find(string $email): ?LoginAttempt
    {
        return LoginAttempt::query()
            ->where('email', $this->normalize($email))
            ->first();
    }

    private function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
