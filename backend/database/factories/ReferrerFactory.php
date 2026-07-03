<?php

namespace Database\Factories;

use App\Models\Referrer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Referrer>
 */
class ReferrerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'relationship_tier' => 'warm',
            'commission_pct' => 10,
            'agreed_terms' => true,
            'status' => 'active',
            'password' => '12345678', // hashed by the model cast
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending', 'password' => null]);
    }

    public function paused(): static
    {
        return $this->state(['status' => 'paused']);
    }
}
