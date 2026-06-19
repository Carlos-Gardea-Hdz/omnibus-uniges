<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Lifecycle of a single uploaded graduation document (SPEC §6.3.13).
 *
 * pending  → the required row exists but nothing has been uploaded yet
 * uploaded → the student has uploaded a file, awaiting staff review
 * approved → reviewer accepted the file
 * rejected → reviewer rejected the file with a reason; student re-uploads
 */
#[TypeScript]
enum DocumentStatus: string
{
    case Pending = 'pending';
    case Uploaded = 'uploaded';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /** i18n key consumed by the bilingual frontend (document_status.*). */
    public function labelKey(): string
    {
        return 'document_status.'.$this->value;
    }

    /** Semantic colour token for badges (maps to CSS theme tokens). */
    public function color(): string
    {
        return match ($this) {
            self::Rejected => 'danger',
            self::Approved => 'success',
            self::Uploaded => 'primary',
            self::Pending => 'warning',
        };
    }
}
