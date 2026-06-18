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
                'user' => $request->user()?->only(['id', 'name', 'email']),
            ],
            'flash' => [
                'success' => fn (): ?string => $this->flashString($request, 'success'),
                'error' => fn (): ?string => $this->flashString($request, 'error'),
            ],
            'locale' => app()->getLocale(),
        ];
    }

    private function flashString(Request $request, string $key): ?string
    {
        $value = $request->session()->get($key);

        return is_string($value) ? $value : null;
    }
}
