<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer;
use Spatie\TypeScriptTransformer\Collectors\DefaultCollector;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\Writers\ModuleWriter;

return [
    /*
     * Scan the whole app — Spatie Data DTOs and #[TypeScript] enums live
     * under app/Domain/**. Output is a single typed module consumed by the
     * frontend (single source of truth: Laravel → React).
     */
    'auto_discover_types' => [
        app_path(),
    ],

    'collectors' => [
        DefaultCollector::class,
    ],

    'transformers' => [
        DataTypeScriptTransformer::class,
        EnumTransformer::class,
    ],

    'default_type_replacements' => [
        DateTime::class => 'string',
        CarbonInterface::class => 'string',
        CarbonImmutable::class => 'string',
        Carbon\Carbon::class => 'string',
    ],

    'writer' => ModuleWriter::class,

    'output_file' => resource_path('js/types/generated.d.ts'),

    'formatter' => null,

    'transform_to_native_enums' => true,
];
