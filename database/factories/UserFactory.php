<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Student,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function student(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => UserRole::Student]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => UserRole::Admin]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => UserRole::SuperAdmin]);
    }

    public function secretary(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => UserRole::Secretary]);
    }

    public function assistantSecretary(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => UserRole::AssistantSecretary]);
    }

    public function schoolServices(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => UserRole::SchoolServices]);
    }
}
