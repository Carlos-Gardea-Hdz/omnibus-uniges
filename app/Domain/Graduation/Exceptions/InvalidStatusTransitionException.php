<?php

declare(strict_types=1);

namespace App\Domain\Graduation\Exceptions;

use App\Domain\Graduation\Enums\GraduationStatus;
use DomainException;

final class InvalidStatusTransitionException extends DomainException
{
    public static function between(GraduationStatus $from, GraduationStatus $to): self
    {
        return new self(
            "Illegal graduation transition: {$from->value} → {$to->value}. ".
            'States must advance sequentially.'
        );
    }
}
