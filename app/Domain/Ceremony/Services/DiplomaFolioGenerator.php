<?php

declare(strict_types=1);

namespace App\Domain\Ceremony\Services;

use App\Domain\Ceremony\Models\DiplomaFolioSequence;
use App\Domain\Ceremony\ValueObjects\DiplomaFolio;
use App\Domain\Graduation\Models\Student;

/**
 * Mints the next diploma folio for a graduating student (SPEC §3.5 GRAD-03).
 *
 * Folio format: `{YEAR}-{PROGRAM_CODE}-{SEQ}`, unique per program per year,
 * `SEQ` zero-padded to three digits (`001`).
 *
 * RACE SAFETY (MANDATORY): `generate()` MUST be invoked inside the calling
 * Action's `DB::transaction` so the row lock survives. It `firstOrCreate`s the
 * per-(program, year) row, then re-reads it with `lockForUpdate()` — covering
 * the lost-update window on create — before incrementing. Never `count()+1`.
 */
final class DiplomaFolioGenerator
{
    public function generate(Student $student): DiplomaFolio
    {
        $program = $student->program;
        $year = now()->year;

        DiplomaFolioSequence::query()->firstOrCreate([
            'program_id' => $program->id,
            'year' => $year,
        ]);

        $sequence = DiplomaFolioSequence::query()
            ->where('program_id', $program->id)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $next = $sequence->last_value + 1;
        $sequence->last_value = $next;
        $sequence->save();

        $seq = str_pad((string) $next, 3, '0', STR_PAD_LEFT);

        return new DiplomaFolio(
            folio: "{$year}-{$program->code}-{$seq}",
            book: (string) $year,
            sheet: $seq,
        );
    }
}
