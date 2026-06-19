<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // A diploma folio is a legal registry identifier and must be unique.
        // Defense-in-depth behind the row-locked DiplomaFolioGenerator: a lost
        // update fails loud (constraint violation) instead of silently
        // corrupting the ledger. Partial so the many NULL (not-yet-graduated)
        // rows never collide.
        DB::statement(
            'CREATE UNIQUE INDEX students_diploma_folio_unique '
            .'ON students (diploma_folio) WHERE diploma_folio IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS students_diploma_folio_unique');
    }
};
