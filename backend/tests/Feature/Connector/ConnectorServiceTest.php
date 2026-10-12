<?php

namespace Tests\Feature\Connector;

use App\Models\ActivityLog;
use App\Models\PricingConfig;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The MCP connector's services surface (v6). In connector language a SERVICE is
 * a service category (Web, Dashboard …) and a PACKAGE is a card inside one.
 * Read everything (Live or Hidden); create HIDDEN; partially update while still
 * Hidden. A package may be wired to the quote builder with a NEW quote package
 * key — never one another package row (Live or Hidden) or the pricing JSON
 * already uses, since the engine resolves keys on hidden rows too.
 */
class ConnectorServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    private ServiceCategory $web;

    protected function setUp(): void
    {
        parent::setUp();
        $this->founder = User::factory()->founder()->create();

        PricingConfig::factory()->create([
            'config' => [
                'currency' => 'MYR',
                'valid_for_days' => 30,
                'rush_multiplier' => 1.20,
                'base_packages' => ['legacy_pages' => ['min' => 800, 'max' => 1200, 'eta_value' => 1, 'eta_unit' => 'week']],
                'modifiers' => [],
                'addons' => [],
            ],
        ]);

        $this->web = $this->category(['slug' => 'web', 'name' => 'Websites', 'active' => true, 'sort_order' => 0]);
        $this->package(['slug' => 'business', 'name' => 'Business', 'quote_key' => ['category' => 'web', 'package' => 'web_business']]);
    }

    /** @param  list<string>  $abilities */
    private function tokenHeader(array $abilities = ['connector:read', 'connector:draft']): array
    {
        $token = $this->founder->createToken('mcp-connector', $abilities)->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function category(array $overrides = []): ServiceCategory
    {
        return ServiceCategory::forceCreate(array_merge([
            'slug' => 'dashboards',
            'name' => 'Dashboards',
            'icon' => 'i-lucide-layout-dashboard',
            'description' => 'Admin systems and portals.',
            'sort_order' => 1,
            'active' => false,
        ], $overrides));
    }

    private function package(array $overrides = []): ServicePackage
    {
        return ServicePackage::forceCreate(array_merge([
            'service_category_id' => $this->web->id,
            'slug' => 'essential',
            'name' => 'Essential',
            'tagline' => 'A clean starter site.',
            'price_min_myr' => 3500,
            'price_max_myr' => 5500,
            'unit' => 'per project',
            'duration_text' => '2 weeks',
            'eta_value' => 2,
            'eta_unit' => 'week',
            'features' => ['4–6 pages'],
            'sort_order' => 0,
            'active' => true,
        ], $overrides));
    }

    private function servicePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'AI Automation',
            'icon' => 'i-lucide-bot',
            'description' => 'Workflows that answer, route and follow up for you.',
        ], $overrides);
    }

    private function packagePayload(array $overrides = []): array
    {
        return array_merge([
            'service_id' => $this->web->id,
            'name' => 'Landing Sprint',
            'tagline' => 'One page, live in a week.',
            'price_min_myr' => 1800,
            'price_max_myr' => 2600,
            'unit' => 'per project',
            'duration_text' => '1 week',
            'eta_value' => 1,
            'eta_unit' => 'week',
            'features' => ['One long-scroll page', 'Contact form'],
            'cta' => 'Get a quote',
        ], $overrides);
    }

    // ── Access ──────────────────────────────────────────────────────────────

    public function test_a_cockpit_token_is_rejected(): void
    {
        $token = $this->founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        $this->getJson('/api/v1/connector/services', ['Authorization' => "Bearer {$token}"])->assertForbidden();
    }

    public function test_a_read_only_token_can_read_but_not_write(): void
    {
        $package = ServicePackage::firstOrFail();
        $headers = $this->tokenHeader(['connector:read']);

        $this->getJson('/api/v1/connector/services', $headers)->assertOk();
        $this->getJson("/api/v1/connector/services/{$this->web->id}", $headers)->assertOk();
        $this->getJson("/api/v1/connector/packages/{$package->id}", $headers)->assertOk();
        $this->postJson('/api/v1/connector/services', $this->servicePayload(), $headers)->assertForbidden();
        $this->putJson("/api/v1/connector/services/{$this->web->id}", ['name' => 'X'], $headers)->assertForbidden();
        $this->postJson('/api/v1/connector/packages', $this->packagePayload(), $headers)->assertForbidden();
        $this->putJson("/api/v1/connector/packages/{$package->id}", ['name' => 'X'], $headers)->assertForbidden();
    }

    // ── Read ────────────────────────────────────────────────────────────────

    public function test_list_returns_every_service_with_its_packages(): void
    {
        $hidden = $this->category(['slug' => 'hidden-svc', 'name' => 'Hidden Svc']);
        $this->package(['service_category_id' => $hidden->id, 'slug' => 'draft', 'active' => false]);

        $res = $this->getJson('/api/v1/connector/services', $this->tokenHeader())->assertOk();

        $services = collect($res->json('data'))->keyBy('slug');
        $this->assertSame('live', $services['web']['visibility']);
        $this->assertSame('hidden', $services['hidden-svc']['visibility']);
        $business = collect($services['web']['packages'])->firstWhere('slug', 'business');
        $this->assertSame('web_business', $business['quote_package_key']);
        $this->assertTrue($business['quotable']);
        $this->assertSame('hidden', $services['hidden-svc']['packages'][0]['visibility']);
    }

    public function test_get_package_returns_the_full_record(): void
    {
        $package = ServicePackage::where('slug', 'business')->firstOrFail();

        $this->getJson("/api/v1/connector/packages/{$package->id}", $this->tokenHeader())
            ->assertOk()
            ->assertJsonPath('data.name', 'Business')
            ->assertJsonPath('data.service.slug', 'web')
            ->assertJsonPath('data.quote_package_key', 'web_business')
            ->assertJsonPath('data.visibility', 'live')
            ->assertJsonPath('data.admin_url', rtrim((string) config('services.frontend.url'), '/')."/admin/services/packages/{$package->id}");
    }

    public function test_unknown_ids_are_404_with_a_hint(): void
    {
        $headers = $this->tokenHeader();

        $this->getJson('/api/v1/connector/services/999', $headers)
            ->assertNotFound()->assertJsonPath('message', 'No service with id 999. Use list_services to find the right one.');
        $this->getJson('/api/v1/connector/packages/999', $headers)
            ->assertNotFound()->assertJsonPath('message', 'No package with id 999. Use list_services to find the right one.');
    }

    // ── Services (categories) ───────────────────────────────────────────────

    public function test_create_service_is_hidden_never_default_and_appended(): void
    {
        $res = $this->postJson('/api/v1/connector/services', $this->servicePayload([
            'active' => true,
            'is_default' => true,
        ]), $this->tokenHeader())->assertCreated();

        $service = ServiceCategory::findOrFail($res->json('data.id'));
        $this->assertFalse($service->active);
        $this->assertFalse($service->is_default);
        $this->assertSame('ai-automation', $service->slug);
        $this->assertSame(1, $service->sort_order);
        $res->assertJsonPath('data.visibility', 'hidden');
        $this->assertSame('mcp_connector', ActivityLog::where('action', 'service_category.created')->firstOrFail()->changes['via']);
    }

    public function test_create_service_requires_a_lucide_icon(): void
    {
        $this->postJson('/api/v1/connector/services', $this->servicePayload(['icon' => '🤖']), $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('icon');
    }

    public function test_update_service_is_partial_while_hidden_and_refused_when_live(): void
    {
        $hidden = $this->category();
        $headers = $this->tokenHeader();

        $this->putJson("/api/v1/connector/services/{$hidden->id}", ['description' => 'New copy.'], $headers)->assertOk();
        $hidden->refresh();
        $this->assertSame('New copy.', $hidden->description);
        $this->assertSame('Dashboards', $hidden->name);
        $this->assertFalse($hidden->active);

        $this->putJson("/api/v1/connector/services/{$this->web->id}", ['name' => 'Renamed'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('visibility');
        $this->assertSame('Websites', $this->web->fresh()->name);
    }

    public function test_renaming_a_hidden_service_slug_repoints_its_wired_packages(): void
    {
        $hidden = $this->category();
        $package = $this->package(['service_category_id' => $hidden->id, 'slug' => 'starter', 'active' => false, 'quote_key' => ['category' => 'dashboards', 'package' => 'dash_new']]);

        $this->putJson("/api/v1/connector/services/{$hidden->id}", ['slug' => 'portals'], $this->tokenHeader())
            ->assertOk()
            ->assertJsonPath('data.slug', 'portals');

        $this->assertEquals(['category' => 'portals', 'package' => 'dash_new'], $package->fresh()->quote_key);
    }

    // ── Packages ────────────────────────────────────────────────────────────

    public function test_create_package_is_hidden_and_appended_to_its_service(): void
    {
        $res = $this->postJson('/api/v1/connector/packages', $this->packagePayload([
            'active' => true,
            'featured' => true,
        ]), $this->tokenHeader())->assertCreated();

        $package = ServicePackage::findOrFail($res->json('data.id'));
        $this->assertFalse($package->active);
        $this->assertFalse($package->featured);
        $this->assertSame('landing-sprint', $package->slug);
        $this->assertSame(1, $package->sort_order);
        $this->assertNull($package->quote_key);
        $res->assertJsonPath('data.visibility', 'hidden')->assertJsonPath('data.quotable', false);
        $this->assertSame('mcp_connector', ActivityLog::where('action', 'service_package.created')->firstOrFail()->changes['via']);
    }

    public function test_create_package_into_a_hidden_service(): void
    {
        $hidden = $this->category();

        $this->postJson('/api/v1/connector/packages', $this->packagePayload(['service_id' => $hidden->id]), $this->tokenHeader())
            ->assertCreated()
            ->assertJsonPath('data.service.id', $hidden->id)
            ->assertJsonPath('data.sort_order', 0);
    }

    public function test_create_package_validates_the_card(): void
    {
        $this->postJson('/api/v1/connector/packages', $this->packagePayload([
            'service_id' => 999,
            'price_max_myr' => 100,
            'eta_unit' => 'year',
            'features' => [],
        ]), $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_id', 'price_max_myr', 'eta_unit', 'features']);
    }

    public function test_quote_package_key_wires_the_package_to_the_quote_builder(): void
    {
        $res = $this->postJson('/api/v1/connector/packages', $this->packagePayload([
            'quote_package_key' => 'web_landing_sprint',
        ]), $this->tokenHeader())->assertCreated();

        $package = ServicePackage::findOrFail($res->json('data.id'));
        $this->assertEquals(['category' => 'web', 'package' => 'web_landing_sprint'], $package->quote_key);
        // Wired, but only quotable once the founder makes it Live.
        $res->assertJsonPath('data.quote_package_key', 'web_landing_sprint')
            ->assertJsonPath('data.quotable', false);
    }

    public function test_quote_package_key_already_on_a_package_is_refused(): void
    {
        $this->postJson('/api/v1/connector/packages', $this->packagePayload([
            'quote_package_key' => 'web_business',
        ]), $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quote_package_key')
            ->assertJsonPath('errors.quote_package_key.0', fn (string $m) => str_contains($m, 'Business'));
    }

    public function test_quote_package_key_held_by_a_hidden_package_is_refused(): void
    {
        $this->package(['slug' => 'ghost', 'name' => 'Ghost', 'active' => false, 'quote_key' => ['category' => 'web', 'package' => 'web_ghost']]);

        $this->postJson('/api/v1/connector/packages', $this->packagePayload(['quote_package_key' => 'web_ghost']), $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quote_package_key');
    }

    public function test_quote_package_key_in_the_pricing_config_is_refused(): void
    {
        $this->postJson('/api/v1/connector/packages', $this->packagePayload(['quote_package_key' => 'legacy_pages']), $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quote_package_key');
    }

    public function test_quote_package_key_must_be_snake_case(): void
    {
        $this->postJson('/api/v1/connector/packages', $this->packagePayload(['quote_package_key' => 'Web Landing']), $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quote_package_key');
    }

    public function test_quote_package_key_needs_a_max_price(): void
    {
        $this->postJson('/api/v1/connector/packages', $this->packagePayload([
            'price_max_myr' => null,
            'quote_package_key' => 'web_landing_sprint',
        ]), $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price_max_myr');
    }

    public function test_update_package_is_partial_and_keeps_its_own_key(): void
    {
        $package = $this->package(['slug' => 'draft', 'active' => false, 'quote_key' => ['category' => 'web', 'package' => 'web_draft']]);

        $this->putJson("/api/v1/connector/packages/{$package->id}", [
            'tagline' => 'Sharper.',
            'quote_package_key' => 'web_draft',
        ], $this->tokenHeader())->assertOk();

        $package->refresh();
        $this->assertSame('Sharper.', $package->tagline);
        $this->assertSame('Essential', $package->name);
        $this->assertSame('web_draft', $package->quote_key['package']);
        $this->assertSame('mcp_connector', ActivityLog::where('action', 'service_package.updated')->firstOrFail()->changes['via']);
    }

    public function test_update_package_can_unlink_the_quote_builder(): void
    {
        $package = $this->package(['slug' => 'draft', 'active' => false, 'quote_key' => ['category' => 'web', 'package' => 'web_draft']]);

        $this->putJson("/api/v1/connector/packages/{$package->id}", ['quote_package_key' => null], $this->tokenHeader())
            ->assertOk()
            ->assertJsonPath('data.quotable', false);

        $this->assertNull($package->fresh()->quote_key);
    }

    public function test_update_package_cannot_drop_the_max_price_of_a_quotable_package(): void
    {
        $package = $this->package(['slug' => 'draft', 'active' => false, 'quote_key' => ['category' => 'web', 'package' => 'web_draft']]);

        $this->putJson("/api/v1/connector/packages/{$package->id}", ['price_max_myr' => null], $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price_max_myr');
    }

    public function test_update_package_moves_it_to_another_service(): void
    {
        $dash = $this->category(['slug' => 'dash']);
        $this->package(['service_category_id' => $dash->id, 'slug' => 'starter', 'active' => false]);
        $package = $this->package(['slug' => 'draft', 'sort_order' => 2, 'active' => false, 'quote_key' => ['category' => 'web', 'package' => 'web_draft']]);

        $this->putJson("/api/v1/connector/packages/{$package->id}", ['service_id' => $dash->id], $this->tokenHeader())
            ->assertOk()
            ->assertJsonPath('data.service.id', $dash->id);

        $package->refresh();
        $this->assertSame($dash->id, $package->service_category_id);
        $this->assertSame(1, $package->sort_order);
        $this->assertEquals(['category' => 'dash', 'package' => 'web_draft'], $package->quote_key);
    }

    public function test_update_package_is_refused_while_live(): void
    {
        $package = ServicePackage::where('slug', 'business')->firstOrFail();

        $this->putJson("/api/v1/connector/packages/{$package->id}", ['price_min_myr' => 1], $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('visibility');

        $this->assertSame('3500.00', $package->fresh()->price_min_myr);
    }

    public function test_package_slug_is_unique_within_its_service(): void
    {
        $this->postJson('/api/v1/connector/packages', $this->packagePayload(['slug' => 'business']), $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }
}
