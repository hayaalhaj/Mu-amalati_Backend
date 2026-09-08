<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'transaction_id' => Transaction::factory(),
            'title' => fake()->sentence(3),
            'message' => fake()->sentence(),
            'type' => NotificationType::ReviewRequired,
            'is_read' => false,
            'read_at' => null,
        ];
    }

    /**
     * Indicate that the notification is read.
     */
    public function read(): static
    {
        return $this->state(fn () => [
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
