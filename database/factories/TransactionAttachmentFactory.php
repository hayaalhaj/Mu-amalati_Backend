<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\TransactionAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionAttachment>
 */
class TransactionAttachmentFactory extends Factory
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
            'uploaded_by' => User::factory(),
            'original_name' => fake()->word().'.pdf',
            'file_path' => 'transactions/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1000, 500000),
        ];
    }
}
