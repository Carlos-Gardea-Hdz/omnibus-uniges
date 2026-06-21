<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Soft-delete + FULL unique trap (WARN 4). On a softDeletes() table a FULL
     * unique index also covers trashed rows, so a value can never be reused after
     * its owner is soft-deleted (a 23505 on re-create). The diploma_folio index
     * already models the fix correctly with a PARTIAL index; this mirrors it for
     * every identity/business-key column on a soft-deletable table, scoping
     * uniqueness to LIVE rows (WHERE deleted_at IS NULL).
     *
     * Additive + reversible: drop the full unique index, add the partial one.
     *
     * @var array<string, list<string>>
     */
    private const TARGETS = [
        'students' => ['control_number', 'user_id'],
        'professors' => ['email'],
        'programs' => ['code'],
        'graduation_types' => ['code'],
        'departments' => ['code'],
        'study_plans' => ['code'],
    ];

    public function up(): void
    {
        foreach (self::TARGETS as $table => $columns) {
            foreach ($columns as $column) {
                $name = "{$table}_{$column}_unique";

                // Laravel's ->unique() created a table-level UNIQUE CONSTRAINT
                // (not a bare index); a PARTIAL unique cannot be a constraint, so
                // drop the constraint (it drops its backing index) and replace it
                // with a partial unique INDEX scoped to live rows.
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
                DB::statement(
                    "CREATE UNIQUE INDEX {$name} ON {$table} ({$column}) "
                    .'WHERE deleted_at IS NULL'
                );
            }
        }
    }

    public function down(): void
    {
        foreach (self::TARGETS as $table => $columns) {
            foreach ($columns as $column) {
                $name = "{$table}_{$column}_unique";

                // Restore the original full table-level UNIQUE CONSTRAINT.
                DB::statement("DROP INDEX IF EXISTS {$name}");
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} UNIQUE ({$column})");
            }
        }
    }
};
