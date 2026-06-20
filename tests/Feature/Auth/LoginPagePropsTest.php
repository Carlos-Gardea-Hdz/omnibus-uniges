<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/*
 * Runtime contract test for the Auth/Login Inertia page (CONTRACT §12). Inertia
 * props are untyped at runtime, so the static gates cannot catch a controller
 * that serialises a different shape than the React page consumes. The login
 * screen carries no page-specific props of its own: it renders for a guest with
 * only the globally-shared payload (auth.user === null, locale, flash). This
 * locks that exact shape. Runs against PostgreSQL 18 via RefreshDatabase.
 */

it('renders the Auth/Login component for a guest', function (): void {
    get(route('login'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Auth/Login'),
        );
});

it('shares a null auth.user and a locale on the login screen for a guest', function (): void {
    get(route('login'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Auth/Login')
                ->where('auth.user', null)
                ->has('locale')
                ->has('flash'),
        );
});

it('exposes no page-specific props on the login screen beyond the shared payload', function (): void {
    get(route('login'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Auth/Login')
                ->missing('user')
                ->missing('email')
                ->missing('status'),
        );
});
