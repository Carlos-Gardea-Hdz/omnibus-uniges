<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\DemoLoginController;
use App\Support\DemoContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo-session lifecycle + write guard (SPEC §13). Aliased `demo` and applied
 * AFTER `auth`, BEFORE `role`, so it only ever runs for an authenticated request
 * and can read the demo flags stamped by {@see DemoLoginController}.
 *
 * For a real (non-demo) session this is a strict no-op — real users are never
 * affected. For a demo session it:
 *   1. Expires the sandbox once its 30-minute TTL elapses (logout + invalidate
 *      + token rotation + redirect to the landing screen).
 *   2. Publishes the per-session isolation tag into {@see DemoContext} so the
 *      DemoScope global scope confines every Eloquent read/write to this
 *      visitor's own rows.
 *   3. BLOCKS the irreversible, side-effecting routes (folio-minting graduation
 *      today; the {@see self::DESTRUCTIVE_ROUTE_NAMES} list is the single
 *      documented extension point for future delete/import/settings routes) with
 *      a graceful 302 + flash — no mutation occurs.
 *   4. Slides the TTL forward on every active request.
 */
final class DemoSessionMiddleware
{
    /**
     * Routes whose effects are irreversible or escape the session sandbox and so
     * are forbidden inside demo mode. The 8→9 graduation transition mints a
     * permanent diploma folio (SPEC §13, §004) — the canonical destructive op.
     * Extend this list for future delete/import/settings routes.
     *
     * @var list<string>
     */
    private const DESTRUCTIVE_ROUTE_NAMES = [
        'admin.graduation.ceremony.graduate',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('is_demo') !== true) {
            return $next($request);
        }

        $expiresAtRaw = $request->session()->get('demo_expires_at');
        $expiresAt = is_numeric($expiresAtRaw) ? (int) $expiresAtRaw : 0;

        if (now()->timestamp > $expiresAt) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('landing')->with('error', __('demo.expired'));
        }

        $sessionId = $request->session()->get('demo_session_id');
        app(DemoContext::class)->set(is_string($sessionId) ? $sessionId : null);

        if (in_array($request->route()?->getName(), self::DESTRUCTIVE_ROUTE_NAMES, strict: true)) {
            return back()->with('error', __('demo.blocked'));
        }

        $request->session()->put('demo_expires_at', now()->addMinutes(30)->timestamp);

        return $next($request);
    }
}
