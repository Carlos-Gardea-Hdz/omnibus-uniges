<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistent brute-force ledger backing the progressive login block
 * (SPEC §6.3.2 + §10.3, AUTH-01). Exactly one row per email: `failed_attempts`
 * is the consecutive-failure counter (reset on success) and `times_blocked` is
 * the monotonic escalation counter (never reset) that drives the
 * `60s × times_blocked` (max 15 min) cooldown. `updated_at` is the timestamp
 * the cooldown is measured against — there is intentionally no `created_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->unsignedInteger('times_blocked')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
