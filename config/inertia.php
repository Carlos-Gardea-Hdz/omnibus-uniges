<?php

declare(strict_types=1);

return [
    /*
     * Server-side rendering. Build with `pnpm build:ssr`, then run the SSR
     * server with `php artisan inertia:start-ssr`. Disabled by default in
     * local dev; enable in production for fast first paint + SEO.
     */
    'ssr' => [
        'enabled' => env('INERTIA_SSR_ENABLED', false),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
    ],

    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [
            resource_path('js/Pages'),
        ],
        'page_extensions' => [
            'tsx',
        ],
    ],
];
