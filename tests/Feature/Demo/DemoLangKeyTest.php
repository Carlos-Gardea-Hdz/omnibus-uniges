<?php

declare(strict_types=1);

/*
 * Translation-key regression guard for demo mode (CONTRACT §12, spec §2.7 /
 * scenario 14). The demo controller / middleware / rate-limiter flash or throw
 * 'demo.expired', 'demo.blocked' and 'demo.throttled'. Prior slices repeatedly
 * shipped with keys that resolved to the raw dotted string because the lang file
 * entry was missing — this locks that every demo flash key actually RESOLVES to
 * a translated string in BOTH locales (memory rule: every __() key present in
 * lang/es.json AND lang/en.json). Boots the app for the translator; no database.
 */

it('resolves every demo flash key to a real translation in both locales', function (string $key): void {
    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        expect(__($key))->not->toBe($key);
    }
})->with([
    'demo.expired' => ['demo.expired'],
    'demo.blocked' => ['demo.blocked'],
    'demo.throttled' => ['demo.throttled'],
]);

it('resolves the demo flash keys to a non-empty string in both locales', function (string $key): void {
    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $message = __($key);

        // A real entry resolves to a non-empty translated string, never the raw
        // dotted key (the symptom of a missing lang entry in prior slices).
        expect($message)->toBeString()
            ->and($message)->not->toBe('')
            ->and($message)->not->toBe($key);
    }
})->with([
    'demo.expired' => ['demo.expired'],
    'demo.blocked' => ['demo.blocked'],
    'demo.throttled' => ['demo.throttled'],
]);
