<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\TransactionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionType>
 */
class TransactionTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_en' => fake()->unique()->words(2, true),
            'name_ar' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'destination_department_id' => Department::factory(),
            'requires_attachment' => false,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the type requires an attachment.
     */
    public function requiresAttachment(): static
    {
        return $this->state(fn () => [
            'requires_attachment' => true,
        ]);
    }

    /**
     * Indicate that the type is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
