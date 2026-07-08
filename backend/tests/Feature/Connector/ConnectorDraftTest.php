<?php

namespace Tests\Feature\Connector;

use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\ServiceScopeField;
use App\Models\User;
use App\Services\Quoting\DocumentMapper;
use App\Services\Quoting\PricingEngine;
use App\Services\Quoting\QuoteRequestInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The scoped MCP-connector surface (v3): read the catalog, LIST + read back ANY
 * non-deleted quotation, create DRAFT quotations, and UPDATE any pre-send one.
 * Priced drafts must be priced by the SAME PricingEngine as the public funnel;
 * the write ability must not leak into the admin surface, read-only tokens must
 * not draft, and soft-deleted rows must never surface through any read.
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

    public function test_get_reads_back_any_non_connector_quotation(): void
    {
        // v3: reads are open — a funnel/admin-created row reads back too, not only
        // connector-created ones (the old `document.created_via` scoping is gone).
        $quotation = Quotation::factory()->create([
            'reference_code' => 'AXNQ-2099-0002',
            'pricing_config_id' => PricingConfig::getActive()->id,
            'document' => ['layout' => 'standard', 'items' => []],
        ]);

        $this->getJson('/api/v1/connector/quotations/'.$quotation->reference_code, $this->tokenHeader(['connector:read']))
            ->assertOk()
            ->assertJsonPath('data.reference_code', 'AXNQ-2099-0002');
    }

    public function test_get_404s_for_a_soft_deleted_quotation(): void
    {
        // Soft-deleted rows must never surface through any connector read.
        $quotation = Quotation::factory()->create([
            'reference_code' => 'AXNQ-2099-0003',
            'pricing_config_id' => PricingConfig::getActive()->id,
        ]);
        $quotation->delete();

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

    public function test_draft_accepts_and_stores_project_title_and_intro(): void
    {
        $res = $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Titled Co', 'email' => 'titled@example.com'],
            'package_key' => 'test_landing',
            'project' => 'Acme brand website',
            'intro' => 'A fast, clean marketing site.',
        ], $this->connectorHeader())->assertCreated();

        $res->assertJsonPath('data.project', 'Acme brand website');
        $res->assertJsonPath('data.intro', 'A fast, clean marketing site.');

        $q = Quotation::where('reference_code', $res->json('data.reference_code'))->firstOrFail();
        $this->assertSame('Acme brand website', $q->document['project']);
        $this->assertSame('A fast, clean marketing site.', $q->document['intro']);
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

    public function test_detailed_proposal_draft_is_section_priced_and_renders(): void
    {
        $res = $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Detailed Co', 'email' => 'detailed@example.com'],
            'project' => 'Acme website',
            'intro' => 'A clean marketing site.',
            'detailed' => [
                'subtitle' => 'Website quotation',
                'sections' => [
                    ['title' => 'Design', 'rows' => [
                        ['title' => 'Brand + UI', 'amount_myr' => 3000],
                        ['title' => 'Prototype', 'amount_myr' => 1000],
                    ]],
                    ['title' => 'Build', 'rows' => [
                        ['title' => 'Front-end', 'amount_myr' => 6000],
                    ]],
                ],
                'included' => [['eyebrow' => 'SEO', 'items' => ['Sitemap', 'Meta tags']]],
                'options' => [['badge' => 'OPTION A', 'title' => 'Standard', 'amount_myr' => 4000, 'recommended' => true]],
                'care' => [['label' => 'Basic', 'detail' => 'Hosting', 'amount_myr' => 200, 'period' => 'month']],
            ],
        ], $this->connectorHeader())->assertCreated();

        $res->assertJsonPath('data.layout', 'detailed');

        $q = Quotation::where('reference_code', $res->json('data.reference_code'))->firstOrFail();

        // Section-priced: min == max == Σ row amounts (3000 + 1000 + 6000).
        $this->assertEquals(10000, $q->estimate_min_myr);
        $this->assertEquals(10000, $q->estimate_max_myr);
        $this->assertSame(0, $q->estimate_eta_value); // admin sets the timeline
        $this->assertNull($q->package_key);
        $this->assertSame([], $q->form_payload['packages']);

        $doc = $q->document;
        $this->assertSame('detailed', $doc['layout']);
        $this->assertCount(2, $doc['payload']['sections']);
        $this->assertSame('Acme website', $doc['payload']['project']);
        $this->assertNotEmpty($doc['payload']['included']);
        $this->assertNotEmpty($doc['payload']['options']['cards']);
        $this->assertNotEmpty($doc['payload']['care']['rows']);

        // The document-driven PDF mapper renders it as a detailed, sum-priced quote.
        $this->assertSame(10000.0, Quotation::sumDetailedSections($doc));
        $this->assertSame('detailed', DocumentMapper::toDocumentData($q->fresh())['layout']);
    }

    public function test_detailed_cannot_combine_with_a_package(): void
    {
        $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Conflict Co', 'email' => 'conflict@example.com'],
            'package_key' => 'test_landing',
            'detailed' => ['sections' => [['title' => 'x', 'rows' => [['title' => 'y', 'amount_myr' => 100]]]]],
        ], $this->connectorHeader())
            ->assertStatus(422)
            ->assertJsonValidationErrors('detailed');
    }

    // ── list_quotations ──────────────────────────────────────────────────────

    /** Create a priced draft via the connector; return its reference code. */
    private function createPricedDraft(string $email, array $modifiers = []): string
    {
        return $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Acme', 'email' => $email],
            'package_key' => 'test_landing',
            'modifiers' => $modifiers,
        ], $this->connectorHeader())->assertCreated()->json('data.reference_code');
    }

    public function test_list_returns_slim_rows_newest_first(): void
    {
        $first = $this->createPricedDraft('one@example.com');
        $second = $this->createPricedDraft('two@example.com');

        $body = $this->getJson('/api/v1/connector/quotations', $this->tokenHeader(['connector:read']))
            ->assertOk()
            ->json();

        // Newest first (second draft leads).
        $this->assertSame($second, $body['data'][0]['reference_code']);
        $this->assertSame($first, $body['data'][1]['reference_code']);

        // Slim rows — identity + estimate + admin URL, never the heavy payloads.
        $row = $body['data'][0];
        $this->assertSame('two@example.com', $row['client']['email']);
        $this->assertArrayHasKey('estimate', $row);
        $this->assertArrayHasKey('admin_url', $row);
        $this->assertArrayNotHasKey('form_payload', $row);
        $this->assertArrayNotHasKey('document', $row);
        $this->assertArrayNotHasKey('line_items', $row);
    }

    public function test_list_filters_by_status_and_excludes_soft_deleted(): void
    {
        $draftRef = $this->createPricedDraft('draft@example.com'); // status=draft
        $sent = Quotation::factory()->create([
            'reference_code' => 'AXNQ-2097-0001',
            'pricing_config_id' => PricingConfig::getActive()->id,
            'status' => 'sent',
        ]);
        $deleted = Quotation::factory()->create([
            'reference_code' => 'AXNQ-2097-0002',
            'pricing_config_id' => PricingConfig::getActive()->id,
            'status' => 'draft',
        ]);
        $deleted->delete();

        // status[]=sent returns only the sent row.
        $sentOnly = $this->getJson('/api/v1/connector/quotations?status[]=sent', $this->tokenHeader(['connector:read']))
            ->assertOk()->json('data');
        $this->assertSame(['AXNQ-2097-0001'], array_column($sentOnly, 'reference_code'));

        // No filter → every non-deleted row, but never the soft-deleted one.
        $all = $this->getJson('/api/v1/connector/quotations', $this->tokenHeader(['connector:read']))
            ->assertOk()->json('data');
        $refs = array_column($all, 'reference_code');
        $this->assertContains($draftRef, $refs);
        $this->assertContains('AXNQ-2097-0001', $refs);
        $this->assertNotContains('AXNQ-2097-0002', $refs);
    }

    public function test_list_search_matches_name_email_and_reference(): void
    {
        Quotation::factory()->create(['reference_code' => 'AXNQ-2095-0001', 'name' => 'Zed Widgets', 'email' => 'zed@example.com', 'pricing_config_id' => PricingConfig::getActive()->id]);
        Quotation::factory()->create(['reference_code' => 'AXNQ-2095-0002', 'name' => 'Other Co', 'email' => 'other@example.com', 'pricing_config_id' => PricingConfig::getActive()->id]);

        // Name match.
        $byName = $this->getJson('/api/v1/connector/quotations?q=Zed', $this->tokenHeader(['connector:read']))->assertOk()->json('data');
        $this->assertSame(['AXNQ-2095-0001'], array_column($byName, 'reference_code'));

        // Reference-code match.
        $byRef = $this->getJson('/api/v1/connector/quotations?q=2095-0002', $this->tokenHeader(['connector:read']))->assertOk()->json('data');
        $this->assertSame(['AXNQ-2095-0002'], array_column($byRef, 'reference_code'));
    }

    public function test_list_filters_by_created_date_range(): void
    {
        Quotation::factory()->create(['reference_code' => 'AXNQ-2094-0001', 'submitted_at' => '2026-01-10 09:00:00', 'pricing_config_id' => PricingConfig::getActive()->id]);
        Quotation::factory()->create(['reference_code' => 'AXNQ-2094-0002', 'submitted_at' => '2026-03-20 09:00:00', 'pricing_config_id' => PricingConfig::getActive()->id]);

        $jan = $this->getJson('/api/v1/connector/quotations?from=2026-01-01&to=2026-02-01', $this->tokenHeader(['connector:read']))
            ->assertOk()->json('data');

        $this->assertSame(['AXNQ-2094-0001'], array_column($jan, 'reference_code'));
    }

    public function test_list_paginates_and_caps_per_page_at_25(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Quotation::factory()->create(['pricing_config_id' => PricingConfig::getActive()->id]);
        }

        $page1 = $this->getJson('/api/v1/connector/quotations?per_page=2&page=1', $this->tokenHeader(['connector:read']))->assertOk()->json();
        $this->assertCount(2, $page1['data']);
        $this->assertSame(2, $page1['meta']['per_page']);
        $this->assertSame(3, $page1['meta']['total']);
        $this->assertSame(2, $page1['meta']['last_page']);

        // Over-cap per_page is silently clamped to 25 (never a 422).
        $capped = $this->getJson('/api/v1/connector/quotations?per_page=100', $this->tokenHeader(['connector:read']))->assertOk()->json();
        $this->assertSame(25, $capped['meta']['per_page']);
    }

    public function test_read_endpoints_are_throttled(): void
    {
        // The 60/min throttle is applied to the read group (header proves it's on the route).
        $this->getJson('/api/v1/connector/catalog', $this->tokenHeader(['connector:read']))
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '60');
    }

    // ── update_draft_quotation ───────────────────────────────────────────────

    public function test_update_reprices_a_pre_send_draft_and_stamps_last_updated_via(): void
    {
        $ref = $this->createPricedDraft('grow@example.com', ['test_pages' => 6]); // +1 page over free 5
        $before = Quotation::where('reference_code', $ref)->firstOrFail()->estimate_max_myr;

        $res = $this->putJson("/api/v1/connector/quotations/{$ref}", [
            'client' => ['name' => 'Acme', 'email' => 'grow@example.com'],
            'package_key' => 'test_landing',
            'modifiers' => ['test_pages' => 10], // +5 pages → pricier
        ], $this->connectorHeader())->assertOk();

        $res->assertJsonPath('data.last_updated_via', 'mcp_connector');
        $res->assertJsonPath('document_reseeded', true);

        $q = Quotation::where('reference_code', $ref)->firstOrFail();
        $this->assertGreaterThan((float) $before, (float) $q->estimate_max_myr);
        $this->assertSame('mcp_connector', $q->form_payload['source_meta']['last_updated_via']);
        // created_via stays sticky (still connector-created).
        $this->assertSame('mcp_connector', $q->form_payload['source_meta']['created_via']);
    }

    /** @return array<string, list<string>> */
    public static function postSendStatuses(): array
    {
        return [
            'sent' => ['sent'],
            'accepted' => ['accepted'],
            'rejected' => ['rejected'],
            'expired' => ['expired'],
        ];
    }

    #[DataProvider('postSendStatuses')]
    public function test_update_is_refused_after_send(string $status): void
    {
        $quotation = Quotation::factory()->create([
            'pricing_config_id' => PricingConfig::getActive()->id,
            'status' => $status,
        ]);

        $res = $this->putJson("/api/v1/connector/quotations/{$quotation->reference_code}", [
            'client' => ['name' => 'Acme', 'email' => 'locked@example.com'],
            'package_key' => 'test_landing',
        ], $this->connectorHeader())->assertStatus(422);

        // The refusal names the status + that only a draft is updatable.
        $message = $res->json('message');
        $this->assertStringContainsString("'{$status}'", $message);
        $this->assertStringContainsString('draft', $message);
    }

    public function test_update_is_allowed_on_a_draft_regardless_of_creator(): void
    {
        // A funnel/admin-created draft (source=admin, no connector provenance) is
        // updatable — the gate is lifecycle (draft), not origin.
        $quotation = Quotation::factory()->create([
            'pricing_config_id' => PricingConfig::getActive()->id,
            'status' => 'draft',
            'document' => ['layout' => 'standard', 'items' => []],
        ]);

        $this->putJson("/api/v1/connector/quotations/{$quotation->reference_code}", [
            'client' => ['name' => 'Acme', 'email' => 'presend@example.com'],
            'package_key' => 'test_landing',
        ], $this->connectorHeader())->assertOk();

        $quotation->refresh();
        $this->assertSame('draft', $quotation->status); // update doesn't change status
        $this->assertSame('mcp_connector', $quotation->form_payload['source_meta']['last_updated_via']);
    }

    public function test_update_404s_for_an_unknown_reference_code(): void
    {
        $this->putJson('/api/v1/connector/quotations/AXNQ-1999-9999', [
            'client' => ['name' => 'Acme', 'email' => 'nope@example.com'],
            'package_key' => 'test_landing',
        ], $this->connectorHeader())->assertNotFound();
    }

    public function test_update_reseeds_a_pristine_connector_document(): void
    {
        $ref = $this->createPricedDraft('pristine@example.com', ['test_pages' => 6]);
        $q = Quotation::where('reference_code', $ref)->firstOrFail();
        // Base line seeded at midpoint(1500,2500) = 2000, plus a "+1 page" scope line.
        $this->assertContains(2000, array_map('intval', array_column($q->document['items'], 'rate')));

        // Update to 12 pages, no reseed flag — the untouched connector doc reseeds.
        $this->putJson("/api/v1/connector/quotations/{$ref}", [
            'client' => ['name' => 'Acme', 'email' => 'pristine@example.com'],
            'package_key' => 'test_landing',
            'modifiers' => ['test_pages' => 12], // +7 pages × 100 = +700 scope line
        ], $this->connectorHeader())->assertOk()->assertJsonPath('document_reseeded', true);

        $q->refresh();
        $this->assertContains(700, array_map('intval', array_column($q->document['items'], 'rate')));
    }

    public function test_update_preserves_an_admin_edited_document_then_reseeds_on_flag(): void
    {
        $ref = $this->createPricedDraft('edited@example.com', ['test_pages' => 6]);
        $q = Quotation::where('reference_code', $ref)->firstOrFail();

        // Simulate an admin hand-editing the document line items.
        $edited = $q->document;
        $edited['items'][0]['title'] = 'Bespoke landing (hand-tuned)';
        $edited['items'][0]['rate'] = 9999;
        $q->update(['document' => $edited]);

        // Update without reseed_document → the edited document is preserved, only re-priced.
        $res = $this->putJson("/api/v1/connector/quotations/{$ref}", [
            'client' => ['name' => 'Acme', 'email' => 'edited@example.com'],
            'package_key' => 'test_landing',
            'modifiers' => ['test_pages' => 20],
        ], $this->connectorHeader())->assertOk();
        $res->assertJsonPath('document_reseeded', false);

        $q->refresh();
        $this->assertSame('Bespoke landing (hand-tuned)', $q->document['items'][0]['title']);
        $this->assertSame(9999, (int) $q->document['items'][0]['rate']);

        // Now with reseed_document: true → the document is regenerated from scope.
        $this->putJson("/api/v1/connector/quotations/{$ref}", [
            'client' => ['name' => 'Acme', 'email' => 'edited@example.com'],
            'package_key' => 'test_landing',
            'modifiers' => ['test_pages' => 20],
            'reseed_document' => true,
        ], $this->connectorHeader())->assertOk()->assertJsonPath('document_reseeded', true);

        $q->refresh();
        $this->assertSame('Landing', $q->document['items'][0]['title']);
    }
}
