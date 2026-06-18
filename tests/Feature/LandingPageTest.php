<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('renders the Welcome page via Inertia with shared props', function (): void {
    get('/')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Welcome')
                ->has('appVersion')
                ->has('locale'),
        );
});

it('sets baseline security headers on every response', function (): void {
    get('/')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});
