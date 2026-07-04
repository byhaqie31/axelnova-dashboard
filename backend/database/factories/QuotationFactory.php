<?php

namespace Database\Factories;

use App\Models\PricingConfig;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 *
 * Default reference codes use a fake year (2001) so they never collide with
 * codes the ReferenceCodeGenerator tests mint for real years.
 */
class QuotationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference_code' => 'AXNQ-2001-'.fake()->unique()->numerify('####'),
            'source' => 'admin',
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'pricing_config_id' => PricingConfig::factory(),
            'form_payload' => [],
            'estimate_min_myr' => 1500,
            'estimate_max_myr' => 2500,
            'estimate_eta_value' => 2,
            'estimate_eta_unit' => 'week',
            'status' => 'draft',
            'submitted_at' => now(),
        ];
    }
}
