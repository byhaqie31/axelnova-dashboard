<?php

namespace Tests\Feature\Pricing;

use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A quote package key (quote_key.package) belongs to ONE service package. The
 * engine resolves a key's id / tagline / category on hidden rows too, so a second
 * row holding the same key — even a hidden one — would silently re-point quotes.
 * Taking over a legacy pricing-JSON key stays allowed here (that is how DB rows
 * replace JSON entries); only the connector refuses JSON keys.
 */
class AdminServicePackageQuoteKeyTest extends TestCase
{
    use RefreshDatabase;

    private ServiceCategory $web;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;
        $this->headers = ['Authorization' => "Bearer {$token}"];

        $this->web = ServiceCategory::forceCreate([
            'slug' => 'web', 'name' => 'Websites', 'icon' => 'i-lucide-globe', 'description' => 'Sites.', 'active' => true,
        ]);
        ServicePackage::forceCreate([
            'service_category_id' => $this->web->id,
            'slug' => 'business',
            'name' => 'Business',
            'tagline' => 'Multi-page site.',
            'price_min_myr' => 3500,
            'price_max_myr' => 5500,
            'unit' => 'per project',
            'duration_text' => '2 weeks',
            'eta_value' => 2,
            'eta_unit' => 'week',
            'features' => ['Pages'],
            'quote_key' => ['category' => 'web', 'package' => 'web_business'],
            'active' => false,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'service_category_id' => $this->web->id,
            'slug' => 'premium',
            'name' => 'Premium',
            'tagline' => 'Everything.',
            'price_min_myr' => 8000,
            'price_max_myr' => 12000,
            'unit' => 'per project',
            'duration_text' => '4 weeks',
            'eta_value' => 4,
            'eta_unit' => 'week',
            'features' => ['All of it'],
            'quote_key' => ['category' => 'web', 'package' => 'web_premium'],
        ], $overrides);
    }

    public function test_a_key_already_held_by_another_package_is_refused(): void
    {
        $this->postJson('/api/v1/admin/service-packages', $this->payload([
            'quote_key' => ['category' => 'web', 'package' => 'web_business'],
        ]), $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quote_key.package');
    }

    public function test_a_package_keeps_its_own_key_on_update(): void
    {
        $package = ServicePackage::where('slug', 'business')->firstOrFail();

        $this->putJson("/api/v1/admin/service-packages/{$package->id}", $this->payload([
            'slug' => 'business',
            'quote_key' => ['category' => 'web', 'package' => 'web_business'],
        ]), $this->headers)->assertOk();
    }

    public function test_a_new_key_is_accepted(): void
    {
        $this->postJson('/api/v1/admin/service-packages', $this->payload(), $this->headers)->assertCreated();
    }
}
