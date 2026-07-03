<?php

namespace Database\Factories;

use App\Models\PricingConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingConfig>
 *
 * Minimal but realistic pricing JSON — one base package, one boolean modifier,
 * one threshold modifier, one addon. Tests override `config` for scenarios.
 */
class PricingConfigFactory extends Factory
{
    public function definition(): array
    {
        return [
            'version' => 'test-'.fake()->unique()->numerify('####'),
            'active' => true,
            'config' => [
                'currency' => 'MYR',
                'valid_for_days' => 30,
                'rush_multiplier' => 1.20,
                'base_packages' => [
                    'landing' => ['min' => 1500, 'max' => 2500, 'eta_value' => 2, 'eta_unit' => 'week'],
                ],
                'modifiers' => [],
                'addons' => [],
            ],
        ];
    }
}
