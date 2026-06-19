<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based authorization gate (SPEC §3.1, ROLE-01).
 *
 * Authorization lives in HTTP middleware — never in the domain. Each route
 * declares the roles allowed to reach it via the 'role' alias, e.g.
 * `->middleware('role:admin,super_admin,secretary')`. The authenticated
 * user's {@see UserRole} is matched by its string value; a miss is a 403.
 */
final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role;

        if (! $role instanceof UserRole || ! in_array($role->value, $roles, strict: true)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
