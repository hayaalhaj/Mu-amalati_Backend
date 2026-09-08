<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'description' => fake()->sentence(),
            'manager_id' => null,
            'is_active' => true,
        ];
    }

    /**
     * Assign a manager to the department.
     */
    public function withManager(): static
    {
        return $this->state(fn () => [
            'manager_id' => User::factory()->manager(),
        ]);
    }

    /**
     * Indicate that the department is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
