<?php

declare(strict_types=1);

use App\Domain\Graduation\Exceptions\InvalidStatusTransitionException;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            SecurityHeadersMiddleware::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An illegal 9-state graduation transition is a domain guard, not a
        // server fault: surface it as a graceful 302 redirect-back with a field
        // error (the project's validation convention), or 422 for JSON clients —
        // never an unhandled 500. Applies across every Graduation/Jury slice.
        $exceptions->render(function (InvalidStatusTransitionException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['status' => $e->getMessage()]);
        });
    })->create();
