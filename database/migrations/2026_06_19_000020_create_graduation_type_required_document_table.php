<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduation_type_required_document', function (Blueprint $table): void {
            $table->foreignId('graduation_type_id')
                ->constrained('graduation_types')
                ->cascadeOnDelete();
            $table->foreignId('required_document_id')
                ->constrained('required_documents')
                ->cascadeOnDelete();

            $table->primary(['graduation_type_id', 'required_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduation_type_required_document');
    }
};
