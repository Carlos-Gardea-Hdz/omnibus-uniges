<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Data;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\File;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Student upload payload for a single required document.
 *
 * Spatie Data is the single source of truth for validation and the generated
 * TypeScript type. The attributes enforce only the global 10MB cap; the
 * per-document MIME/size rules (from required_documents.allowed_mimes /
 * max_size_kb) are enforced in UploadDocumentAction because they are dynamic.
 */
#[TypeScript]
final class UploadDocumentData extends Data
{
    public function __construct(
        #[Required, IntegerType, Exists('required_documents', 'id')]
        public int $required_document_id,
        #[Required, File, Max(10240)]
        public UploadedFile $file,
    ) {}
}
