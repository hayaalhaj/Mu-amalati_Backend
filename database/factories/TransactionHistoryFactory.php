<?php

namespace Database\Factories;

use App\Enums\TransactionHistoryAction;
use App\Models\Transaction;
use App\Models\TransactionHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionHistory>
 */
class TransactionHistoryFactory extends Factory
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
            'performed_by' => User::factory(),
            'action' => TransactionHistoryAction::Created,
            'old_status' => null,
            'new_status' => 'draft',
            'workflow_step_name' => null,
            'comment' => fake()->sentence(),
        ];
    }
}
