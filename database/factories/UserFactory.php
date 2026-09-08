<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Department;
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
            'password' => static::$password ??= Hash::make('password'),
            'phone' => fake()->optional()->numerify('+491#########'),
            'job_title' => fake()->jobTitle(),
            'role' => UserRole::Employee,
            'department_id' => Department::factory(),
            'status' => UserStatus::Active,
            'annual_leave_days' => 30,
            'used_leave_days' => fake()->numberBetween(0, 10),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is an employee.
     */
    public function employee(?Department $department = null): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Employee,
            'department_id' => $department?->id ?? Department::factory(),
        ]);
    }

    /**
     * Indicate that the user is a manager.
     */
    public function manager(?Department $department = null): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Manager,
            'department_id' => $department?->id ?? Department::factory(),
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Admin,
            'department_id' => null,
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => UserStatus::Inactive,
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
