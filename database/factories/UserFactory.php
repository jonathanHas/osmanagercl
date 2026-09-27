<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
        ];
    }

    /**
     * Give the user a role, creating it if the test has not seeded one.
     *
     * Most feature tests only need a user who can reach the page under test;
     * 'admin' short-circuits every permission check in HasPermissions, so no
     * role_permissions rows are needed.
     */
    public function withRole(string $name): static
    {
        return $this->state(fn () => [
            'role_id' => \App\Models\Role::firstOrCreate(
                ['name' => $name],
                ['display_name' => ucfirst($name)]
            )->id,
        ]);
    }

    /**
     * Give the user a Shop PIN. The default is the one the cycle-26 tests use.
     */
    public function withPin(string $pin = '2580'): static
    {
        return $this->state(fn () => [
            'pin_hash' => Hash::make($pin),
            'pin_length' => strlen($pin),
            'pin_set_at' => now(),
        ]);
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
}
