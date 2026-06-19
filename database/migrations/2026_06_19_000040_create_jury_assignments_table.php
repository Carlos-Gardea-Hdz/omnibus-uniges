<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jury_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('students')->restrictOnDelete();
            $table->foreignId('president_professor_id')->constrained('professors')->restrictOnDelete();
            $table->foreignId('secretary_professor_id')->constrained('professors')->restrictOnDelete();
            $table->foreignId('vocal_professor_id')->constrained('professors')->restrictOnDelete();
            $table->foreignId('substitute_professor_id')->nullable()->constrained('professors')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // PostgreSQL CHECK: the three core jury members must be distinct, and the
        // optional substitute (when present) must differ from each of them. The
        // database is the authoritative last line — `IS NULL OR <>` keeps the
        // nullable substitute from tripping the constraint when absent.
        DB::statement(
            'ALTER TABLE jury_assignments ADD CONSTRAINT jury_assignments_distinct_professors_check CHECK ('
            .'president_professor_id <> secretary_professor_id '
            .'AND president_professor_id <> vocal_professor_id '
            .'AND secretary_professor_id <> vocal_professor_id '
            .'AND (substitute_professor_id IS NULL OR substitute_professor_id <> president_professor_id) '
            .'AND (substitute_professor_id IS NULL OR substitute_professor_id <> secretary_professor_id) '
            .'AND (substitute_professor_id IS NULL OR substitute_professor_id <> vocal_professor_id))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('jury_assignments');
    }
};
