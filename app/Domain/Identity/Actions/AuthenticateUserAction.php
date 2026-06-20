<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Data\LoginData;
use App\Domain\Identity\Exceptions\LoginThrottledException;
use App\Domain\Identity\Services\LoginThrottle;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Authenticate a user by email + password under the progressive brute-force
 * block (SPEC §3.1 AUTH-01, §10.3). One business operation:
 *
 *   1. Reject up front if the email is inside its cooldown window.
 *   2. Attempt the credentials via the session guard.
 *   3. On failure: record the attempt and surface a single, uniform error
 *      ("same error for not-found + wrong-password") so the response never
 *      reveals whether the account exists.
 *   4. On success: clear the consecutive-failure counter and return the User.
 *
 * Session regeneration and the redirect are the HTTP layer's responsibility;
 * this Action stays free of Illuminate\Http and simply returns the authenticated
 * User (or throws a ValidationException keyed on `email`).
 */
final class AuthenticateUserAction
{
    public function __construct(
        private readonly LoginThrottle $throttle,
    ) {}

    /**
     * @throws ValidationException uniform credential error, or remaining-cooldown notice
     */
    public function handle(LoginData $data): User
    {
        // Canonicalize the email so the credential check and the throttle ledger
        // agree on one value (users.email is stored lowercased). Without this a
        // user typing a different-cased email locks themselves out under the
        // lowercased throttle key while Auth::attempt fails the case-sensitive
        // column match.
        $email = mb_strtolower(trim($data->email));

        $this->assertNotThrottled($email);

        $authenticated = Auth::attempt(
            ['email' => $email, 'password' => $data->password],
            $data->remember,
        );

        if (! $authenticated) {
            $this->throttle->recordFailure($email);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $this->throttle->clear($email);

        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * @throws ValidationException if the email is still inside its cooldown window
     */
    private function assertNotThrottled(string $email): void
    {
        try {
            $this->throttle->assertNotBlocked($email);
        } catch (LoginThrottledException $exception) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $exception->secondsRemaining]),
            ]);
        }
    }
}
