<?php

namespace Database\Factories;

use App\Models\ExternalAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalAccount>
 */
class ExternalAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'referrer',
            'email' => fake()->unique()->safeEmail(),
            'password' => '12345678', // hashed by the model cast
            'status' => 'active',
        ];
    }

    public function investor(): static
    {
        return $this->state(['type' => 'investor']);
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }

    /** No passcode issued yet — cannot sign in. */
    public function uncredentialed(): static
    {
        return $this->state(['password' => null]);
    }
}
