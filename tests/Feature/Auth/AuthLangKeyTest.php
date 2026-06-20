<?php

declare(strict_types=1);

/*
 * Translation-key regression guard. The login controller flashes / throws the
 * 'auth.failed' and 'auth.throttle' keys (SPEC §3.1 AUTH-01). Prior slices
 * repeatedly shipped with translation keys that resolved to the raw dotted
 * string because the lang file entry was missing. This locks that a
 * representative auth key actually RESOLVES to a translated string in BOTH
 * locales — never the raw key. No database needed: pure config + lang files.
 */

it('resolves auth.failed to a real translation, not the raw key, in English', function (): void {
    app()->setLocale('en');

    expect(__('auth.failed'))->not->toBe('auth.failed');
});

it('resolves auth.failed to a real translation, not the raw key, in Spanish', function (): void {
    app()->setLocale('es');

    expect(__('auth.failed'))->not->toBe('auth.failed');
});

it('resolves the throttle key with its :seconds placeholder in both locales', function (): void {
    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $message = __('auth.throttle', ['seconds' => 60]);

        expect($message)->not->toBe('auth.throttle');
        expect($message)->not->toContain(':seconds');
    }
});
