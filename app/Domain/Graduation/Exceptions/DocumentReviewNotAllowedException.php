<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Exceptions;

use App\Domain\Graduation\Enums\GraduationStatus;
use DomainException;

/**
 * A document approval/rejection was attempted while the owning student is not in
 * the document-review stage (AnnexIiiPending). The {studentDocument} route binds
 * the document directly, so without this guard staff could mutate a document for
 * a student already past the review stage — corrupting a later workflow state.
 */
final class DocumentReviewNotAllowedException extends DomainException
{
    public static function notInReviewStage(GraduationStatus $current): self
    {
        return new self(
            "Documents can only be reviewed while the student is in {$current->value}: ".
            'the student must be in '.GraduationStatus::AnnexIiiPending->value.'.'
        );
    }
}
