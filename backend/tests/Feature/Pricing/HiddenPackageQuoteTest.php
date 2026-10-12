<?php

namespace Tests\Feature\Pricing;

use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A hidden package can't be quoted — not even when its key is ALSO in the
 * legacy pricing JSON. The DB row claims the key either way, so hiding it (or
 * its service) drops the key from the merged config instead of falling back to
 * the old JSON price. The public quote endpoint goes further: it only accepts a
 * package the catalogue offers right now, so a stale cached page or a hand-made
 * request can't quote a hidden package or a legacy JSON-only key.
 */
class HiddenPackageQuoteTest extends TestCase
{
    use RefreshDatabase;

    private ServiceCategory $web;

    private ServicePackage $business;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // don't run the client / admin email jobs

        PricingConfig::factory()->create([
            'config' => [
                'currency' => 'MYR', 'valid_for_days' => 30, 'rush_multiplier' => 1.20, 'modifiers' => [], 'addons' => [],
                // web_business is ALSO a DB package (below); legacy_pages is JSON-only.
                'base_packages' => [
                    'web_business' => ['min' => 3500, 'max' => 5500, 'eta_value' => 2, 'eta_unit' => 'week'],
                    'legacy_pages' => ['min' => 800, 'max' => 1200, 'eta_value' => 1, 'eta_unit' => 'week'],
                ],
            ],
        ]);

        $this->web = ServiceCategory::forceCreate([
            'slug' => 'web', 'name' => 'Websites', 'icon' => 'i-lucide-globe', 'description' => 'Sites.', 'active' => true,
        ]);
        $this->business = ServicePackage::forceCreate([
            'service_category_id' => $this->web->id, 'slug' => 'business', 'name' => 'Business Site', 'tagline' => 'A proper web presence.',
            'price_min_myr' => 5000, 'price_max_myr' => 8000, 'unit' => 'per project', 'duration_text' => '3 weeks',
            'eta_value' => 3, 'eta_unit' => 'week', 'features' => ['Pages'],
            'quote_key' => ['category' => 'web', 'package' => 'web_business'], 'active' => true,
        ]);

        Cache::flush();
    }

    private function quote(string $packageKey): TestResponse
    {
        return $this->postJson('/api/v1/quote-requests', [
            'name' => 'Visitor', 'email' => 'visitor@example.com', 'phone' => '0123456789',
            'package_key' => $packageKey, 'rush' => false, 'form_payload' => ['source' => 'quote_builder'],
        ]);
    }

    private function basePackages(): array
    {
        return $this->getJson('/api/v1/quote-builder/config')->assertOk()->json('data.base_packages');
    }

    public function test_a_live_package_is_priced_from_its_db_row_not_the_json(): void
    {
        $this->assertSame(5000, (int) $this->basePackages()['web_business']['min']);

        $this->quote('web_business')->assertCreated();
        $this->assertSame(1, Quotation::count());
    }

    public function test_hiding_the_service_drops_a_json_backed_key(): void
    {
        $this->web->update(['active' => false]);

        $this->assertArrayNotHasKey('web_business', $this->basePackages());
        $this->quote('web_business')
            ->assertUnprocessable()
            ->assertJsonPath('errors.package_key.0', 'That package is no longer available. Please pick another one from the services page.');
        $this->assertSame(0, Quotation::count());

        // Live again → quotable again, at the DB price.
        $this->web->update(['active' => true]);
        $this->assertSame(5000, (int) $this->basePackages()['web_business']['min']);
        $this->quote('web_business')->assertCreated();
    }

    public function test_hiding_the_package_drops_a_json_backed_key(): void
    {
        $this->business->update(['active' => false]);

        $this->assertArrayNotHasKey('web_business', $this->basePackages());
        $this->quote('web_business')->assertUnprocessable()->assertJsonValidationErrors('package_key');
    }

    public function test_the_mcp_catalogue_drops_a_hidden_json_backed_key(): void
    {
        $this->business->update(['active' => false]);
        $token = User::factory()->founder()->create()->createToken('mcp-connector', ['connector:read'])->plainTextToken;

        $keys = collect($this->getJson('/api/v1/connector/catalog', ['Authorization' => "Bearer {$token}"])->assertOk()->json('packages'))
            ->pluck('key')->all();
        $this->assertNotContains('web_business', $keys);
    }

    public function test_the_public_endpoint_refuses_a_legacy_json_only_key(): void
    {
        // Still in the merged config (old quotes and the admin keep resolving it) …
        $this->assertArrayHasKey('legacy_pages', $this->basePackages());

        // … but no service offers it, so the public endpoint won't quote it.
        $this->quote('legacy_pages')->assertUnprocessable()->assertJsonValidationErrors('package_key');
        $this->assertSame(0, Quotation::count());
    }
}
