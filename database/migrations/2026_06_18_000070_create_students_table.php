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
        Schema::create('students', function (Blueprint $table): void {
            $table->id();

            // Identity & academic relations.
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('control_number', 12)->unique();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignId('graduation_type_id')->constrained('graduation_types')->restrictOnDelete();
            $table->foreignId('study_plan_id')->constrained('study_plans')->restrictOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('professors')->nullOnDelete();

            // Personal data.
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('mother_last_name', 100)->nullable();
            $table->string('gender', 16);
            $table->integer('age')->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('mobile', 15)->nullable();

            // Workflow.
            $table->string('status')->default('form_b_pending');
            $table->jsonb('workflow_metadata')->nullable();

            // Academic record.
            $table->decimal('gpa', 5, 2);
            $table->date('enrollment_date');
            $table->date('graduation_date')->nullable();
            $table->string('thesis_title', 300)->nullable();
            $table->text('thesis_abstract')->nullable();

            // Form B.
            $table->timestamp('form_b_submitted_at')->nullable();
            $table->boolean('form_b_approved')->default(false);
            $table->text('form_b_observations')->nullable();

            // Annexes & documents.
            $table->boolean('annex_iii_completed')->default(false);
            $table->timestamp('documents_completed_at')->nullable();

            // Payment.
            $table->string('payment_reference', 50)->nullable();
            $table->boolean('payment_verified')->default(false);
            $table->timestamp('paid_at')->nullable();

            // Ceremony & diploma.
            $table->timestamp('ceremony_date')->nullable();
            $table->string('ceremony_location', 200)->nullable();
            $table->string('diploma_folio', 50)->nullable();
            $table->string('record_book', 20)->nullable();
            $table->string('record_sheet', 20)->nullable();

            // Address.
            $table->string('address_street', 125)->nullable();
            $table->string('address_neighborhood', 100)->nullable();
            $table->string('address_ext_number', 11)->nullable();
            $table->string('address_int_number', 11)->nullable();
            $table->integer('address_postal_code')->nullable();

            // Misc.
            $table->boolean('is_team_project')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });

        // PostgreSQL CHECK constraints (declarative integrity at the DB layer).
        DB::statement(
            'ALTER TABLE students ADD CONSTRAINT students_control_number_check '
            ."CHECK (control_number ~ '^[0-9]{8,12}$')"
        );
        DB::statement(
            'ALTER TABLE students ADD CONSTRAINT students_gpa_check '
            .'CHECK (gpa BETWEEN 70 AND 100)'
        );
        DB::statement(
            'ALTER TABLE students ADD CONSTRAINT students_gender_check '
            ."CHECK (gender IN ('male', 'female', 'other'))"
        );
        DB::statement(
            'ALTER TABLE students ADD CONSTRAINT students_address_postal_code_check '
            .'CHECK (address_postal_code IS NULL OR address_postal_code BETWEEN 10000 AND 99999)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
