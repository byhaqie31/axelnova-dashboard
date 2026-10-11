<?php

namespace Database\Factories;

use App\Models\CollaborationPartner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CollaborationPartner>
 *
 * A live partner by default (the model's DB default is inactive — a real
 * partner is switched on deliberately; fixtures mostly want a visible one).
 */
class CollaborationPartnerFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'tagline' => fake()->sentence(4),
            'summary' => fake()->sentence(12),
            'story' => fake()->paragraph(),
            'website_url' => fake()->url(),
            'founder_name' => fake()->name(),
            'founder_role' => 'Founder',
            'location' => 'Malaysia',
            'services' => ['Websites', 'Mobile apps'],
            'partner_since' => now()->startOfMonth(),
            'active' => true,
            'sort_order' => 0,
        ];
    }
}
