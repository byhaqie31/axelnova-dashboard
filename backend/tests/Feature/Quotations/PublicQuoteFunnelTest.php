<?php

namespace Tests\Feature\Quotations;

use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\ServiceScopeField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The public quote funnel (POST /v1/quote-requests). Its stored form_payload is
 * now the canonical multi-package shape (one wrapped package), but the visitor-
 * facing HTTP response keeps the flat breakdown it always returned.
 */
class PublicQuoteFunnelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PricingConfig::factory()->create([
            'config' => [
                'currency' => 'MYR', 'valid_for_days' => 30, 'rush_multiplier' => 1.20,
                'base_packages' => [], 'modifiers' => [],
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
        ServiceScopeField::forceCreate([
            'service_category_id' => $category->id, 'field_key' => 'test_pages', 'label' => 'Pages',
            'type' => 'slider', 'applies_to' => [],
            'config' => ['free_threshold' => 5, 'price_per_unit' => 100, 'unit' => 'pages'], 'active' => true,
        ]);

        Cache::flush();
    }

    public function test_funnel_stores_canonical_payload_and_keeps_a_flat_response_breakdown(): void
    {
        Queue::fake(); // don't run the client/admin email jobs

        $res = $this->postJson('/api/v1/quote-requests', [
            'name' => 'Funnel Client', 'email' => 'funnel@example.com', 'phone' => '0123456789',
            'package_key' => 'test_landing',
            'scope_values' => ['test_pages' => 8],
            'addon_keys' => ['seo'],
            'rush' => false,
            'form_payload' => ['source' => 'quote_builder', 'notes' => 'Hello there'],
        ])->assertCreated();

        // Visitor-facing response breakdown is still the FLAT tuple list.
        $breakdown = $res->json('data.breakdown');
        $this->assertSame('Base: Landing', $breakdown[0][0]);
        $this->assertIsInt($breakdown[0][1]);

        $q = Quotation::where('reference_code', $res->json('data.reference_code'))->firstOrFail();

        // Stored form_payload is canonical.
        $this->assertCount(1, $q->form_payload['packages']);
        $this->assertSame('test_landing', $q->form_payload['packages'][0]['package_key']);
        $this->assertSame(['test_pages' => 8], $q->form_payload['packages'][0]['scope_values']);
        $this->assertNotNull($q->form_payload['packages'][0]['service_package_id']);
        $this->assertSame('quote_funnel', $q->form_payload['source_meta']['created_via']);
        // Grouped breakdown.
        $this->assertArrayHasKey('lines', $q->form_payload['breakdown'][0]);
        // Frontend-supplied extras preserved.
        $this->assertSame('quote_builder', $q->form_payload['source']);
        $this->assertSame('Hello there', $q->form_payload['notes']);
        // Scalar column resolved.
        $this->assertNotNull($q->service_package_id);
        // flatBreakdown() (what the email renders) round-trips to the flat tuples.
        $this->assertSame('Base: Landing', $q->flatBreakdown()[0][0]);
    }
}
