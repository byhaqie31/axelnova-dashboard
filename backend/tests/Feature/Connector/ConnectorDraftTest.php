<?php

namespace Tests\Feature\Connector;

use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\ServiceScopeField;
use App\Models\User;
use App\Services\Quoting\PricingEngine;
use App\Services\Quoting\QuoteRequestInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The scoped MCP-connector surface: read the catalog, create DRAFT quotations
 * (priced or bespoke), read them back — and nothing else. Priced drafts must be
 * priced by the SAME PricingEngine as the public funnel; the write ability must
 * not leak into the admin surface, and read-only tokens must not draft.
 */
class ConnectorDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        // Active pricing config: the DB package supplies the base price/ETA, one
        // JSON add-on ('seo') exercises the add-on path.
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

        // A quotable package + a slider scope field (the connector's "modifier").
        $category = ServiceCategory::forceCreate([
            'slug' => 'test-web',
            'name' => 'Test Web',
            'icon' => 'i-lucide-globe',
            'description' => 'Test category',
            'active' => true,
        ]);
        ServicePackage::forceCreate([
            'service_category_id' => $category->id,
            'slug' => 'test-landing-db',
            'name' => 'Landing',
            'tagline' => 'A quick landing page',
            'price_min_myr' => 1500,
            'price_max_myr' => 2500,
            'unit' => 'project',
            'duration_text' => '4 weeks',
            'features' => [],
            'quote_key' => ['package' => 'test_landing'],
            'eta_value' => 4,
            'eta_unit' => 'week',
            'active' => true,
        ]);
        ServiceScopeField::forceCreate([
            'service_category_id' => $category->id,
            'field_key' => 'test_pages',
            'label' => 'Pages',
            'type' => 'slider',
            'applies_to' => [],
            'config' => ['free_threshold' => 5, 'price_per_unit' => 100, 'unit' => 'pages'],
            'active' => true,
        ]);

        $this->founder = User::factory()->founder()->create();

        // Fresh merged-config cache so the catalog reflects the fixtures above.
        Cache::flush();
    }

    /** @param  list<string>  $abilities */
    private function tokenHeader(array $abilities): array
    {
        $token = $this->founder->createToken('mcp-connector', $abilities)->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function connectorHeader(): array
    {
        return $this->tokenHeader(['connector:read', 'connector:draft']);
    }

    public function test_catalog_lists_the_quotable_package_and_its_modifiers(): void
    {
        $body = $this->getJson('/api/v1/connector/catalog', $this->tokenHeader(['connector:read']))
            ->assertOk()
            ->json();

        // Index-agnostic: the seeded catalog may carry other packages/add-ons.
        $package = collect($body['packages'])->firstWhere('key', 'test_landing');
        $this->assertNotNull($package, 'test_landing should be a quotable package');
        $this->assertContains('test_pages', collect($package['modifiers'])->pluck('key')->all());
        $this->assertContains('seo', collect($body['addons'])->pluck('key')->all());
    }

    public function test_priced_draft_matches_the_pricing_engine(): void
    {
        // What the engine produces for the same inputs — the draft must equal it.
        $expected = PricingEngine::active()->calculate(new QuoteRequestInput(
            name: 'x', email: 'x@example.com', phone: '', company: null,
            packageKey: 'test_landing', modifiers: [], addonKeys: ['seo'], rush: false,
            scopeValues: ['test_pages' => 8],
        ));

        $res = $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Acme Sdn Bhd', 'email' => 'acme@example.com'],
            'package_key' => 'test_landing',
            'modifiers' => ['test_pages' => 8],
            'addon_keys' => ['seo'],
            'assumptions' => ['Assumed English + Malay content'],
            'open_questions' => ['Do you have brand assets ready?'],
        ], $this->connectorHeader())->assertCreated();

        $ref = $res->json('data.reference_code');
        $this->assertMatchesRegularExpression('/^AXNQ-\d{4}-\d{4}$/', $ref);
        $res->assertJsonPath('data.status', 'draft');
        $res->assertJsonPath('data.source', 'admin');
        $res->assertJsonPath('data.created_via', 'mcp_connector');

        $quotation = Quotation::where('reference_code', $ref)->firstOrFail();
        $this->assertSame('draft', $quotation->status);
        $this->assertSame('admin', $quotation->source);
        $this->assertEquals($expected->minMyr, $quotation->estimate_min_myr);
        $this->assertEquals($expected->maxMyr, $quotation->estimate_max_myr);
        $this->assertSame($expected->etaValue, $quotation->estimate_eta_value);
        $this->assertSame($expected->etaUnit, $quotation->estimate_eta_unit);
        // The add-on was persisted, and a client was upserted by email.
        $this->assertSame(1, $quotation->addons()->count());
        $this->assertDatabaseHas('clients', ['email' => 'acme@example.com']);
    }

    public function test_bespoke_draft_sums_line_items_and_leaves_eta_blank(): void
    {
        $res = $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Bespoke Co', 'email' => 'bespoke@example.com'],
            'package_key' => null,
            'line_items' => [
                ['label' => 'Custom booking engine', 'description' => 'Bespoke flow', 'amount_myr' => 12000],
                ['label' => 'Data migration', 'amount_myr' => 3000],
            ],
        ], $this->connectorHeader())->assertCreated();

        // ETA presented as null (admin fills it in); amounts checked on the row
        // below to sidestep float/int JSON-encoding ambiguity.
        $res->assertJsonPath('data.estimate.eta_value', null);
        $res->assertJsonPath('data.estimate.eta_unit', null);

        $quotation = Quotation::where('reference_code', $res->json('data.reference_code'))->firstOrFail();
        $this->assertNull($quotation->package_key);
        // min == max == Σ line items.
        $this->assertEquals(15000, $quotation->estimate_min_myr);
        $this->assertEquals(15000, $quotation->estimate_max_myr);
        // Stored as the 0/'week' "no ETA yet" sentinel (columns are NOT NULL).
        $this->assertSame(0, $quotation->estimate_eta_value);
    }

    public function test_unknown_package_key_is_rejected_and_lists_valid_keys(): void
    {
        $res = $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Acme', 'email' => 'acme@example.com'],
            'package_key' => 'not_a_real_key',
        ], $this->connectorHeader())
            ->assertStatus(422)
            ->assertJsonValidationErrors('package_key');

        // The message must name the valid keys so Claude can self-correct.
        $message = $res->json('errors.package_key.0');
        $this->assertStringContainsString('not_a_real_key', $message);
        $this->assertStringContainsString('test_landing', $message);
    }

    public function test_unknown_modifier_key_is_rejected_and_lists_valid_keys(): void
    {
        $res = $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Acme', 'email' => 'acme@example.com'],
            'package_key' => 'test_landing',
            'modifiers' => ['bogus_modifier' => true],
        ], $this->connectorHeader())->assertStatus(422)->assertJsonValidationErrors('modifiers');

        // The error names the valid modifier key for the chosen package.
        $this->assertStringContainsString('test_pages', $res->json('errors.modifiers.0'));
        $this->assertStringContainsString('bogus_modifier', $res->json('errors.modifiers.0'));
    }

    public function test_bespoke_draft_requires_line_items(): void
    {
        $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Acme', 'email' => 'acme@example.com'],
            'package_key' => null,
        ], $this->connectorHeader())
            ->assertStatus(422)
            ->assertJsonValidationErrors('line_items');
    }

    public function test_read_only_token_cannot_draft(): void
    {
        $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Acme', 'email' => 'acme@example.com'],
            'package_key' => null,
            'line_items' => [['label' => 'Thing', 'amount_myr' => 100]],
        ], $this->tokenHeader(['connector:read']))
            ->assertForbidden();

        $this->assertDatabaseCount('quotations', 0);
    }

    public function test_connector_token_is_rejected_by_the_admin_surface(): void
    {
        // The connector token carries connector:* but never `cockpit`, so the
        // admin route group's abilities:cockpit gate rejects it.
        $this->getJson('/api/v1/admin/me', $this->connectorHeader())
            ->assertForbidden();
    }

    public function test_get_reads_back_a_connector_created_draft(): void
    {
        $quotation = Quotation::factory()->create([
            'reference_code' => 'AXNQ-2099-0001',
            'pricing_config_id' => PricingConfig::getActive()->id,
            'document' => ['created_via' => 'mcp_connector', 'line_items' => [], 'assumptions' => ['a'], 'open_questions' => [], 'notes' => null],
        ]);

        $this->getJson('/api/v1/connector/quotations/'.$quotation->reference_code, $this->tokenHeader(['connector:read']))
            ->assertOk()
            ->assertJsonPath('data.reference_code', 'AXNQ-2099-0001')
            ->assertJsonPath('data.created_via', 'mcp_connector')
            ->assertJsonPath('data.assumptions.0', 'a');
    }

    public function test_get_404s_for_a_quotation_not_created_by_the_connector(): void
    {
        $quotation = Quotation::factory()->create([
            'reference_code' => 'AXNQ-2099-0002',
            'pricing_config_id' => PricingConfig::getActive()->id,
            'document' => null,
        ]);

        $this->getJson('/api/v1/connector/quotations/'.$quotation->reference_code, $this->tokenHeader(['connector:read']))
            ->assertNotFound();
    }

    public function test_priced_draft_seeds_a_pdf_ready_canonical_document(): void
    {
        $res = $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Acme', 'email' => 'seeded@example.com'],
            'package_key' => 'test_landing',
            'modifiers' => ['test_pages' => 8],
            'addon_keys' => ['seo'],
        ], $this->connectorHeader())->assertCreated();

        $q = Quotation::where('reference_code', $res->json('data.reference_code'))->firstOrFail();
        $doc = $q->document;

        // Canonical standard document the PDF renders (document.items, not the old
        // connector-only document.line_items).
        $this->assertSame('standard', $doc['layout']);
        $this->assertArrayNotHasKey('line_items', $doc);
        // Base at midpoint(1500, 2500) = 2000, then the +3 pages scope line and the seo add-on.
        $this->assertSame('Landing', $doc['items'][0]['title']);
        $this->assertSame(2000, $doc['items'][0]['rate']);
        $this->assertContains('Addon: SEO setup', array_column($doc['items'], 'title'));
        $this->assertContains(600, array_map('intval', array_column($doc['items'], 'rate'))); // seo, exact
        // created_via + the midpoint assumption survive on the document.
        $this->assertSame('mcp_connector', $doc['created_via']);
        $this->assertStringContainsString('midpoint RM 2,000', $doc['assumptions'][0]);

        // Canonical form_payload carries packages[] with a resolved service_package_id.
        $this->assertCount(1, $q->form_payload['packages']);
        $this->assertSame('test_landing', $q->form_payload['packages'][0]['package_key']);
        $this->assertNotNull($q->form_payload['packages'][0]['service_package_id']);
        $this->assertNotNull($q->service_package_id);
        $this->assertSame('mcp_connector', $q->form_payload['source_meta']['created_via']);
    }

    public function test_priced_draft_line_items_ride_along_as_document_lines_without_changing_the_price(): void
    {
        $res = $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Acme', 'email' => 'extras@example.com'],
            'package_key' => 'test_landing',
            'line_items' => [['label' => 'Copywriting', 'amount_myr' => 800]],
        ], $this->connectorHeader())->assertCreated();

        $q = Quotation::where('reference_code', $res->json('data.reference_code'))->firstOrFail();

        $this->assertContains('Copywriting', array_column($q->document['items'], 'title'));
        // Extras never inflate the engine estimate (base-only range 1500–2500).
        $this->assertEquals(1500, $q->estimate_min_myr);
        $this->assertEquals(2500, $q->estimate_max_myr);
    }

    public function test_multi_package_draft_sums_and_seeds_one_base_line_per_package(): void
    {
        $category = ServiceCategory::where('slug', 'test-web')->firstOrFail();
        ServicePackage::forceCreate([
            'service_category_id' => $category->id,
            'slug' => 'test-second-db',
            'name' => 'Second',
            'tagline' => 'A second package',
            'price_min_myr' => 1000,
            'price_max_myr' => 1000,
            'unit' => 'project',
            'duration_text' => '1 week',
            'features' => [],
            'quote_key' => ['package' => 'test_second'],
            'eta_value' => 1,
            'eta_unit' => 'week',
            'active' => true,
        ]);
        Cache::flush();

        $res = $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Multi', 'email' => 'multi@example.com'],
            'packages' => [
                ['package_key' => 'test_landing'],
                ['package_key' => 'test_second'],
            ],
        ], $this->connectorHeader())->assertCreated();

        $q = Quotation::where('reference_code', $res->json('data.reference_code'))->firstOrFail();

        // Summed: (1500 + 1000) .. (2500 + 1000).
        $this->assertEquals(2500, $q->estimate_min_myr);
        $this->assertEquals(3500, $q->estimate_max_myr);
        $this->assertCount(2, $q->form_payload['packages']);
        // One base line per package (midpoints 2000 and 1000).
        $titles = array_column($q->document['items'], 'title');
        $this->assertContains('Landing', $titles);
        $this->assertContains('Second', $titles);
        // Scalar column carries the first package.
        $this->assertSame('test_landing', $q->package_key);
    }

    public function test_rejects_both_packages_and_top_level_package_key(): void
    {
        $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Both Co', 'email' => 'both@example.com'],
            'package_key' => 'test_landing',
            'packages' => [['package_key' => 'test_landing']],
        ], $this->connectorHeader())
            ->assertStatus(422)
            ->assertJsonValidationErrors('packages');
    }
}
