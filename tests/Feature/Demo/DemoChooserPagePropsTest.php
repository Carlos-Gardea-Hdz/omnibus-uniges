<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\DemoPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/*
 * Inertia prop contract for demo mode (CONTRACT §10/§11/§16, spec scenario 15).
 * Two contracts are locked here because Inertia props are untyped at runtime:
 *
 *   1. The /demo chooser page renders `Auth/DemoChooser` with a `presets` array
 *      of EXACTLY 6 snake_case rows ({ value, role, title_key, description_key,
 *      control_number }), each an enum VALUE string — the shape the React page
 *      consumes.
 *   2. On an authenticated demo session the SHARED `demo` prop is
 *      { active:true, preset, expires_at } — and it NEVER leaks demo_session_id
 *      (the server-side isolation token). For a non-demo session it is null.
 *
 * PostgreSQL 18 via RefreshDatabase.
 */

it('renders the Auth/DemoChooser page with exactly six preset rows', function (): void {
    get(route('demo.create'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Auth/DemoChooser')
                ->has('presets', 6),
        );
});

it('shapes every preset row with snake_case enum-value fields', function (): void {
    get(route('demo.create'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Auth/DemoChooser')
                ->has(
                    'presets.0',
                    fn (AssertableInertia $row): AssertableInertia => $row
                        ->has('value')
                        ->has('role')
                        ->has('title_key')
                        ->has('description_key')
                        ->has('control_number'),
                ),
        );
});

it('serializes the preset values as the raw enum value strings', function (): void {
    get(route('demo.create'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page->where(
                'presets',
                fn ($rows): bool => collect($rows)->pluck('value')->all() === [
                    'sustentante_1',
                    'sustentante_2',
                    'sustentante_3',
                    'sustentante_4',
                    'personal',
                    'admin',
                ],
            ),
        );
});

it('carries the fictional control number on student presets and null on staff presets', function (): void {
    get(route('demo.create'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('presets.0.control_number', '20180001')
                ->where('presets.4.control_number', null)
                ->where('presets.5.control_number', null),
        );
});

it('exposes the shared demo prop on an authenticated demo session without leaking the session id', function (): void {
    $sessionId = (string) Str::uuid7();
    $admin = User::factory()->admin()->create();
    $admin->forceFill(['demo_session_id' => $sessionId])->save();

    $expiresAt = now()->addMinutes(30)->timestamp;

    actingAs($admin)
        ->withSession([
            'is_demo' => true,
            'demo_session_id' => $sessionId,
            'demo_preset' => DemoPreset::Admin->value,
            'demo_expires_at' => $expiresAt,
        ])
        ->get(route('admin.graduation.review'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('demo.active', true)
                ->where('demo.preset', DemoPreset::Admin->value)
                ->has('demo.expires_at')
                // SECURITY: the server-only isolation token must never reach the client.
                ->missing('demo.demo_session_id'),
        );
});

it('shares a null demo prop for a non-demo authenticated user', function (): void {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get(route('admin.graduation.review'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->where('demo', null),
        );
});
