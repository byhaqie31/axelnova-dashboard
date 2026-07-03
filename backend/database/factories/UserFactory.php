<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password', // hashed by the model cast
            'role' => 'engineer',
        ];
    }

    public function founder(): static
    {
        return $this->state(['role' => 'founder']);
    }

    public function partner(): static
    {
        return $this->state(['role' => 'partner']);
    }

    public function marketer(): static
    {
        return $this->state(['role' => 'marketer']);
    }

    public function engineer(): static
    {
        return $this->state(['role' => 'engineer']);
    }
}
