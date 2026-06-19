<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-program, per-year counter backing race-safe diploma folio generation
 * (`{YEAR}-{PROGRAM_CODE}-{SEQ}`, SPEC §3.5 GRAD-03). The unique
 * (program_id, year) pair guarantees a single sequence row that
 * `MarkAsGraduatedAction` locks (`lockForUpdate`) inside its transaction to
 * increment `last_value` atomically — never a `count()+1`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diploma_folio_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['program_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diploma_folio_sequences');
    }
};
