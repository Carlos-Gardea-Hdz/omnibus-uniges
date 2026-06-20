<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\AuthenticateUserAction;
use App\Domain\Identity\Data\LoginData;
use App\Http\Controllers\Controller;
use App\Http\Support\RoleLandingRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Session authentication (SPEC §3.1 AUTH-01 / AUTH-06, §7.1).
 *
 * Anemic by law: the controller shows the login screen, delegates the
 * credential + brute-force-throttle logic to {@see AuthenticateUserAction}
 * (SPEC §10.3, persisted in login_attempts), and redirects each role to its
 * entry screen via {@see RoleLandingRoute} — the SAME mapping the demo-login
 * path uses, never duplicated. No business logic, no queries, no manual
 * validation — {@see LoginData} is the single source of validation truth.
 */
final class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginData $data, AuthenticateUserAction $authenticate): RedirectResponse
    {
        $user = $authenticate->handle($data);

        request()->session()->regenerate();

        return redirect()->intended(route(RoleLandingRoute::for($user->role)));
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
