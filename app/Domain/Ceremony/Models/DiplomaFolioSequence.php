<?php

declare(strict_types=1);

namespace App\Domain\Ceremony\Models;

use App\Domain\Academic\Models\Program;
use Database\Factories\DiplomaFolioSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The per-program, per-year diploma folio counter (SPEC §3.5 GRAD-03). Exactly
 * one row per (program, year); `DiplomaFolioGenerator` locks it
 * (`lockForUpdate`) inside the graduation transaction and increments
 * `last_value` to mint the next sequential — guaranteeing distinct folios under
 * concurrency. No soft deletes: the ledger is append-only.
 *
 * @property int $id
 * @property int $program_id
 * @property int $year
 * @property int $last_value
 * @property-read Program $program
 */
final class DiplomaFolioSequence extends Model
{
    /** @use HasFactory<DiplomaFolioSequenceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'program_id',
        'year',
        'last_value',
    ];

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    protected static function newFactory(): DiplomaFolioSequenceFactory
    {
        return DiplomaFolioSequenceFactory::new();
    }
}
