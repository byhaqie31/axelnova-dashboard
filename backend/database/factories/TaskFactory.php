<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'created_by' => User::factory()->founder(),
            'assignee_id' => null,
            'pay_amount_myr' => null,
            'duration_estimate' => fake()->randomElement(['2h', '1 day', '3 days', null]),
            'deadline' => fake()->optional()->dateTimeBetween('now', '+2 weeks'),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'status' => 'open',
            'notes' => null,
            'completed_at' => null,
            'paid_at' => null,
        ];
    }

    /** Unassigned + open — a pick-up-pool task. */
    public function pooled(): static
    {
        return $this->state(['assignee_id' => null, 'status' => 'open']);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(['assignee_id' => $user->id]);
    }

    /** An extra-on-top bonus (RM), rendered as the card's pending/paid badge. */
    public function withPay(int $amount = 150): static
    {
        return $this->state(['pay_amount_myr' => $amount]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => 'in_progress']);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed', 'completed_at' => now()]);
    }

    public function paymentPending(): static
    {
        return $this->state([
            'status' => 'payment_pending',
            'completed_at' => now(),
            'pay_amount_myr' => 150,
        ]);
    }

    public function paid(): static
    {
        return $this->state([
            'status' => 'paid',
            'completed_at' => now(),
            'paid_at' => now(),
            'pay_amount_myr' => 150,
        ]);
    }
}
