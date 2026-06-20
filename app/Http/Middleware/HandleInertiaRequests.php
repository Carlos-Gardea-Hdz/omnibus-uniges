<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Shared props serialized into EVERY Inertia response.
     *
     * SECURITY: every value here is public (visible in the page payload).
     * Never expose tokens/secrets/unauthorized fields — shape with DTOs.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() === null ? null : [
                    ...$request->user()->only(['id', 'name', 'email']),
                    'role' => $request->user()->role->value,
                ],
            ],
            'flash' => [
                'success' => fn (): ?string => $this->flashString($request, 'success'),
                'error' => fn (): ?string => $this->flashString($request, 'error'),
            ],
            'locale' => app()->getLocale(),
            // Demo mode (slice 006): public-safe banner state for the active demo
            // session, or null for a real (non-demo) session. SECURITY: the server-
            // only isolation token (demo_session_id) is NEVER exposed here.
            'demo' => $this->demoState($request),
        ];
    }

    private function flashString(Request $request, string $key): ?string
    {
        $value = $request->session()->get($key);

        return is_string($value) ? $value : null;
    }

    /**
     * The public-safe demo banner payload, or null when this is not a demo
     * session. Only `active`, `preset` (the enum value) and `expires_at` leave the
     * server — never the isolation token.
     *
     * @return array{active: true, preset: ?string, expires_at: int}|null
     */
    private function demoState(Request $request): ?array
    {
        if ($request->session()->get('is_demo') !== true) {
            return null;
        }

        $preset = $request->session()->get('demo_preset');
        $expiresAt = $request->session()->get('demo_expires_at');

        return [
            'active' => true,
            'preset' => is_string($preset) ? $preset : null,
            'expires_at' => is_numeric($expiresAt) ? (int) $expiresAt : 0,
        ];
    }
}
