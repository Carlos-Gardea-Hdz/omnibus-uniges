<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Slice 006 — Demo Mode. The single migration of the slice: it adds a nullable,
 * indexed `demo_session_id` (a UUIDv7 string) to exactly the four tables that
 * carry ephemeral, per-session demo rows. The tag is the security spine:
 *
 *   - Real rows have `demo_session_id IS NULL`.
 *   - A visitor's sandbox = the rows carrying *their* session tag.
 *   - `demo:cleanup` deletes strictly `WHERE demo_session_id IS NOT NULL`.
 *
 * Baseline catalogs (departments, programs, professors, graduation_types,
 * study_plans, required_documents + pivot) and diploma_folio_sequences are
 * deliberately NOT touched — they are the shared, read-only baseline every demo
 * session references. The primary key continues unchanged (bigint id, ADR-001).
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'users',
        'students',
        'student_documents',
        'jury_assignments',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                // 36 chars = the canonical UUID string length. Nullable so real
                // rows stay NULL; indexed because every demo query filters on it.
                $blueprint->string('demo_session_id', 36)->nullable()->index()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                // Laravel derives the auto-generated index name ({table}_demo_session_id_index)
                // from the current table + column array, so this is reversible per table.
                $blueprint->dropIndex(['demo_session_id']);
                $blueprint->dropColumn('demo_session_id');
            });
        }
    }
};
