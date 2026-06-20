<?php

declare(strict_types=1);

/*
 * Translation-key regression guard for the catalog slice (spec 009 §9, §5; the
 * memory rule). The controllers flash catalogs.created/updated/deleted; the Delete
 * Actions throw CatalogInUseException carrying catalogs.error.in_use; the Update
 * Actions throw a ValidationException carrying catalogs.error.code_taken /
 * catalogs.error.email_taken. Prior slices shipped with keys that resolved to the
 * raw dotted string because the lang entry was missing — this locks that every
 * catalogs.* PHP __() key actually RESOLVES to a translated string in BOTH locales
 * (lang/es.json AND lang/en.json). Boots the app for the translator; no database.
 */

it('resolves every catalog flash/error key to a real translation in both locales', function (string $key): void {
    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $message = __($key);

        expect($message)->toBeString()
            ->and($message)->not->toBe('')   // a missing entry resolves to empty or the key
            ->and($message)->not->toBe($key); // the raw dotted key = the missing-entry symptom
    }
})->with([
    'catalogs.created' => ['catalogs.created'],
    'catalogs.updated' => ['catalogs.updated'],
    'catalogs.deleted' => ['catalogs.deleted'],
    'catalogs.error.in_use' => ['catalogs.error.in_use'],
    'catalogs.error.code_taken' => ['catalogs.error.code_taken'],
    'catalogs.error.email_taken' => ['catalogs.error.email_taken'],
]);
