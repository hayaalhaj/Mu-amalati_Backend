<?php

namespace Database\Factories;

use App\Enums\TransactionPriority;
use App\Enums\TransactionStatus;
use App\Models\Department;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_number' => 'TRX-'.fake()->unique()->year().'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'created_by' => User::factory()->employee(),
            'transaction_type_id' => TransactionType::factory(),
            'source_department_id' => Department::factory(),
            'destination_department_id' => Department::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(TransactionPriority::cases()),
            'status' => TransactionStatus::Draft,
        ];
    }

    /**
     * Indicate that the transaction is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn () => ['status' => TransactionStatus::Draft]);
    }

    /**
     * Indicate that the transaction is pending.
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => TransactionStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Indicate that the transaction is returned.
     */
    public function returned(): static
    {
        return $this->state(fn () => [
            'status' => TransactionStatus::Returned,
            'submitted_at' => now()->subDay(),
            'returned_at' => now(),
        ]);
    }

    /**
     * Indicate that the transaction is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => TransactionStatus::Rejected,
            'submitted_at' => now()->subDays(2),
            'rejected_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the transaction is approved.
     */
    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => TransactionStatus::Approved,
            'submitted_at' => now()->subDays(3),
            'approved_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the transaction is completed.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TransactionStatus::Completed,
            'submitted_at' => now()->subDays(4),
            'approved_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
        ]);
    }
}
