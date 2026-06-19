<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('required_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            // CSV of MIME types, e.g. application/pdf,image/jpeg,image/png.
            $table->string('allowed_mimes', 255);
            $table->integer('max_size_kb')->default(10240);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('required_documents');
    }
};
