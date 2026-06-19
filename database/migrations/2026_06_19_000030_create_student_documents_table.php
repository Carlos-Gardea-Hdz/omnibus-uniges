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
        Schema::create('student_documents', function (Blueprint $table): void {
            $table->id();

            // True child of the student — cascades on student deletion.
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('required_document_id')->constrained('required_documents')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            // File metadata — nullable so a pending row exists before any upload.
            $table->string('file_path', 255)->nullable();
            $table->string('file_token', 255)->unique();
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            // Review workflow.
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_id', 'required_document_id']);
            $table->index('status');
            $table->index(['student_id', 'status']);
        });

        // PostgreSQL CHECK constraint (declarative integrity at the DB layer).
        DB::statement(
            'ALTER TABLE student_documents ADD CONSTRAINT student_documents_status_check '
            ."CHECK (status IN ('pending', 'uploaded', 'approved', 'rejected'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
    }
};
