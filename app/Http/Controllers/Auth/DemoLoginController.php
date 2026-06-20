<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\ProvisionDemoSessionAction;
use App\Domain\Identity\Data\DemoLoginData;
use App\Domain\Identity\Enums\DemoPreset;
use App\Http\Controllers\Controller;
use App\Http\Middleware\DemoSessionMiddleware;
use App\Http\Support\RoleLandingRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Demo-mode entry (SPEC §13). A visitor picks a {@see DemoPreset} and is
 * provisioned a throwaway, session-scoped sandbox: a tagged demo user (and,
 * for student presets, a tagged student in the preset's graduation status).
 *
 * Anemic by law: {@see ProvisionDemoSessionAction} mints the ephemeral rows and
 * the per-session isolation tag inside one transaction (Domain ↛ Illuminate\Http,
 * so it does NOT log the user in); this controller performs the HTTP-layer login,
 * stamps the demo session flags read by {@see DemoSessionMiddleware}
 * and the shared Inertia `demo` prop, and lands the user via {@see RoleLandingRoute}
 * — the SAME role→route map the real-login path uses.
 *
 * SECURITY: the page payload exposes only enum VALUE strings + i18n key handles;
 * the isolation token (`demo_session_id`) is server-only and never leaves here.
 */
final class DemoLoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/DemoChooser', [
            'presets' => collect(DemoPreset::cases())->map(fn (DemoPreset $preset): array => [
                'value' => $preset->value,
                'role' => $preset->role()->value,
                'title_key' => $preset->labelKey(),
                'description_key' => $preset->descriptionKey(),
                'control_number' => $preset->controlNumber(),
            ]),
        ]);
    }

    public function store(DemoLoginData $data, ProvisionDemoSessionAction $action): RedirectResponse
    {
        $result = $action->handle($data);

        Auth::guard('web')->login($result->user);

        session()->put([
            'is_demo' => true,
            'demo_session_id' => $result->demoSessionId,
            'demo_preset' => $result->preset->value,
            'demo_expires_at' => now()->addMinutes(30)->timestamp,
        ]);

        session()->regenerate();

        return redirect()->route(RoleLandingRoute::for($result->preset->role()));
    }
}
