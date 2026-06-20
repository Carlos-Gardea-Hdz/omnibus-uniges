<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\DemoContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Demo mode (slice 006): one DemoContext per request so the
        // DemoSessionMiddleware (the writer) and the DemoScope global scope (the
        // reader) share the SAME instance — otherwise the active demo session tag
        // set by the middleware is lost and the symmetric isolation collapses.
        $this->app->singleton(DemoContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerDemoLoginRateLimiter();
    }

    /**
     * Throttle demo-session provisioning (SPEC §13): 10 sandboxes per IP per
     * hour. This is a SEPARATE limiter from the login brute-force guard
     * (LoginThrottle in the Identity domain) — it protects the unauthenticated
     * /demo-login endpoint from sandbox-spam, not credential stuffing.
     *
     * On exceed it redirects back with a session error keyed on `preset` (the
     * project's web-validation convention: 302 + session errors, never a 429
     * JSON body), so the chooser surfaces it inline like any other field error.
     */
    private function registerDemoLoginRateLimiter(): void
    {
        RateLimiter::for('demo-login', fn (Request $request): Limit => Limit::perHour(10)
            ->by($request->ip())
            ->response(fn (): RedirectResponse => back()->withErrors([
                'preset' => __('demo.throttled'),
            ])));
    }
}
