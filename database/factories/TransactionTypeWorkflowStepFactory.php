<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\TransactionType;
use App\Models\TransactionTypeWorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionTypeWorkflowStep>
 */
class TransactionTypeWorkflowStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_type_id' => TransactionType::factory(),
            'department_id' => Department::factory(),
            'step_order' => 1,
            'name' => fake()->words(2, true),
            'is_final' => false,
        ];
    }
}
