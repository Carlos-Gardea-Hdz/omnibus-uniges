<?php

declare(strict_types=1);

namespace App\Domain\Ceremony\ValueObjects;

/**
 * A generated diploma folio plus the record book/sheet that locate the
 * graduation in the institutional ledger (SPEC §3.5 GRAD-03). Format is
 * `{YEAR}-{PROGRAM_CODE}-{SEQ}` with `SEQ` zero-padded to three digits.
 *
 * Built only by DiplomaFolioGenerator — if it exists it is well-formed:
 *   - `record_book` is the four-digit graduation year.
 *   - `record_sheet` is the zero-padded sequential (e.g. `001`).
 */
final readonly class DiplomaFolio
{
    public function __construct(
        public string $folio,
        public string $book,
        public string $sheet,
    ) {}
}
