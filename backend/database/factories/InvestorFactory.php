<?php

namespace Database\Factories;

use App\Models\ExternalAccount;
use App\Models\Investor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investor>
 */
class InvestorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_account_id' => ExternalAccount::factory()->investor(),
            'name' => fake()->name(),
            'company' => fake()->company(),
        ];
    }
}
