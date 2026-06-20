<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Models\LoginAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginAttempt>
 */
class LoginAttemptFactory extends Factory
{
    protected $model = LoginAttempt::class;

    /**
     * A clean ledger row: no failures recorded yet.
     *
     * Fictional demo data only — never seed real account emails.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'failed_attempts' => 0,
            'times_blocked' => 0,
            'updated_at' => now(),
        ];
    }

    /**
     * A row sitting exactly at the block threshold (5 consecutive failures,
     * blocked once), updated `seconds` ago to position it inside or past the
     * `60s × times_blocked` cooldown window.
     */
    public function blocked(int $secondsAgo = 0): static
    {
        return $this->state(fn (array $attributes): array => [
            'failed_attempts' => 5,
            'times_blocked' => 1,
            'updated_at' => now()->subSeconds($secondsAgo),
        ]);
    }

    /** A row with a specific number of consecutive failures (below the threshold). */
    public function withFailures(int $failures): static
    {
        return $this->state(fn (array $attributes): array => [
            'failed_attempts' => $failures,
            'updated_at' => now(),
        ]);
    }
}
