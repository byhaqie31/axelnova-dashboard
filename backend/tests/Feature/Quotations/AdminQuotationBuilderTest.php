<?php

namespace Tests\Feature\Quotations;

use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\ServiceScopeField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The admin quote-builder write path. Every admin store/update now emits the
 * canonical multi-package form_payload (packages[] + rush + grouped breakdown +
 * source_meta), resolves service_package_id, and the shared DocumentSeeder backs
 * the "Seed line items from scope" endpoint — the same seeder the MCP connector uses.
 */
class AdminQuotationBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PricingConfig::factory()->create([
            'config' => [
                'currency' => 'MYR',
                'valid_for_days' => 30,
                'rush_multiplier' => 1.20,
                'base_packages' => [],
                'modifiers' => [],
                'addons' => ['seo' => ['amount' => 600, 'label' => 'SEO setup']],
            ],
        ]);

        $category = ServiceCategory::forceCreate([
            'slug' => 'test-web', 'name' => 'Test Web', 'icon' => 'i-lucide-globe',
            'description' => 'Test category', 'active' => true,
        ]);
        ServicePackage::forceCreate([
            'service_category_id' => $category->id, 'slug' => 'test-landing-db',
            'name' => 'Landing', 'tagline' => 'A quick landing page',
            'price_min_myr' => 1500, 'price_max_myr' => 2500, 'unit' => 'project',
            'duration_text' => '4 weeks', 'features' => [],
            'quote_key' => ['package' => 'test_landing'], 'eta_value' => 4, 'eta_unit' => 'week', 'active' => true,
        ]);
        ServicePackage::forceCreate([
            'service_category_id' => $category->id, 'slug' => 'test-second-db',
            'name' => 'Second', 'tagline' => 'A second package',
            'price_min_myr' => 1000, 'price_max_myr' => 1000, 'unit' => 'project',
            'duration_text' => '1 week', 'features' => [],
            'quote_key' => ['package' => 'test_second'], 'eta_value' => 1, 'eta_unit' => 'week', 'active' => true,
        ]);
        ServiceScopeField::forceCreate([
            'service_category_id' => $category->id, 'field_key' => 'test_pages', 'label' => 'Pages',
            'type' => 'slider', 'applies_to' => [],
            'config' => ['free_threshold' => 5, 'price_per_unit' => 100, 'unit' => 'pages'], 'active' => true,
        ]);

        Cache::flush();
    }

    private function adminHeaders(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_single_package_store_writes_canonical_payload_and_resolves_service_package_id(): void
    {
        $res = $this->postJson('/api/v1/admin/quotations', [
            'name' => 'Acme Sdn Bhd', 'email' => 'acme@example.com',
            'package_key' => 'test_landing',
            'scope_values' => ['test_pages' => 8],
            'addon_keys' => ['seo'],
            'rush' => false,
        ], $this->adminHeaders())->assertCreated();

        $q = Quotation::where('reference_code', $res->json('data.reference_code'))->firstOrFail();

        // 1500..2500 base + 300 (3 pages) + 600 (seo).
        $this->assertEquals(2400, $q->estimate_min_myr);
        $this->assertEquals(3400, $q->estimate_max_myr);

        $this->assertCount(1, $q->form_payload['packages']);
        $this->assertSame('test_landing', $q->form_payload['packages'][0]['package_key']);
        $this->assertSame(['test_pages' => 8], $q->form_payload['packages'][0]['scope_values']);
        $this->assertSame(['seo'], $q->form_payload['packages'][0]['addon_keys']);
        $this->assertNotNull($q->form_payload['packages'][0]['service_package_id']);
        $this->assertNotNull($q->service_package_id);
        $this->assertSame('admin', $q->form_payload['source_meta']['created_via']);
        // Grouped breakdown, one group.
        $this->assertCount(1, $q->form_payload['breakdown']);
        $this->assertSame('test_landing', $q->form_payload['breakdown'][0]['package_key']);
        // The normalizer reads it back as one package.
        $this->assertCount(1, $q->normalizedForm()['packages']);
    }

    public function test_multi_package_store_sums_estimate_and_keeps_first_package_scalar(): void
    {
        $res = $this->postJson('/api/v1/admin/quotations', [
            'name' => 'Multi Co', 'email' => 'multi@example.com',
            'packages' => [
                ['package_key' => 'test_landing', 'scope_values' => ['test_pages' => 8]],
                ['package_key' => 'test_second'],
            ],
            'rush' => false,
        ], $this->adminHeaders())->assertCreated();

        $q = Quotation::where('reference_code', $res->json('data.reference_code'))->firstOrFail();

        // (1500 + 300) + 1000 .. (2500 + 300) + 1000.
        $this->assertEquals(2800, $q->estimate_min_myr);
        $this->assertEquals(3800, $q->estimate_max_myr);
        $this->assertCount(2, $q->form_payload['packages']);
        $this->assertSame('test_landing', $q->package_key);
    }

    public function test_seed_document_endpoint_returns_a_seeded_document_and_assumptions(): void
    {
        $res = $this->postJson('/api/v1/admin/quotations/seed-document', [
            'package_key' => 'test_landing',
            'scope_values' => ['test_pages' => 8],
            'addon_keys' => ['seo'],
            'rush' => false,
        ], $this->adminHeaders())->assertOk();

        $res->assertJsonPath('document.layout', 'standard');
        $items = $res->json('document.items');
        $this->assertSame('Landing', $items[0]['title']);
        $this->assertSame(2000, $items[0]['rate']); // midpoint(1500, 2500)
        $this->assertContains('Addon: SEO setup', array_column($items, 'title'));
        $this->assertContains(600, array_map('intval', array_column($items, 'rate'))); // seo, exact
        $this->assertStringContainsString('midpoint RM 2,000', $res->json('assumptions.0'));
    }

    public function test_standard_store_without_a_package_is_rejected(): void
    {
        $this->postJson('/api/v1/admin/quotations', [
            'name' => 'No Pkg', 'email' => 'nopkg@example.com',
        ], $this->adminHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('package_key');
    }
}
