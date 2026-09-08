<?php

namespace Database\Factories;

use App\Enums\WorkflowStepStatus;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\TransactionWorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionWorkflowStep>
 */
class TransactionWorkflowStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'department_id' => Department::factory(),
            'original_workflow_step_id' => null,
            'step_order' => 1,
            'name' => fake()->words(2, true),
            'status' => WorkflowStepStatus::Waiting,
        ];
    }

    /**
     * Indicate that the step is pending.
     */
    public function pending(): static
    {
        return $this->state(fn () => ['status' => WorkflowStepStatus::Pending]);
    }

    /**
     * Indicate that the step is approved.
     */
    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => WorkflowStepStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }
}
