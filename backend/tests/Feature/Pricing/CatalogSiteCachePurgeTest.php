<?php

namespace Tests\Feature\Pricing;

use App\Models\PricingConfig;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The public catalogue is instant on the public site: every change a visitor
 * can see — a service category or package that is or was Live (so hiding and
 * showing count), and the add-ons / pricing the /services estimator reads —
 * asks the Nuxt server to drop its cached pages (POST /_cache/purge), once,
 * after the response. Edits to Hidden drafts (the admin's or the MCP's) change
 * nothing public and don't purge.
 */
class CatalogSiteCachePurgeTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'http://axelnova-frontend:3000/_cache/purge';

    private User $founder;

    private ServiceCategory $live;

    private ServiceCategory $hidden;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.site_cache.purge_url' => self::URL, 'services.site_cache.purge_token' => 'secret-token']);
        Http::fake([self::URL => Http::response(['purged' => 3])]);

        $this->founder = User::factory()->founder()->create();

        // Setup rows are created without events, so only the request under test can purge.
        [$this->live, $this->hidden] = ServiceCategory::withoutEvents(fn () => [
            ServiceCategory::forceCreate(['slug' => 'web', 'name' => 'Websites', 'icon' => 'i-lucide-globe', 'description' => 'Sites.', 'active' => true, 'sort_order' => 0]),
            ServiceCategory::forceCreate(['slug' => 'apps', 'name' => 'Apps', 'icon' => 'i-lucide-box', 'description' => 'Apps.', 'active' => false, 'sort_order' => 1]),
        ]);
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->founder->createToken('admin-spa', ['cockpit'])->plainTextToken];
    }

    private function connectorHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->founder->createToken('mcp-connector', ['connector:read', 'connector:draft'])->plainTextToken];
    }

    private function package(ServiceCategory $in, bool $active = true, string $slug = 'starter'): ServicePackage
    {
        return ServicePackage::withoutEvents(fn () => ServicePackage::forceCreate([
            'service_category_id' => $in->id, 'slug' => $slug, 'name' => ucfirst($slug), 'tagline' => 'A site.',
            'price_min_myr' => 2500, 'price_max_myr' => 4000, 'unit' => 'per project', 'duration_text' => '2 weeks',
            'eta_value' => 2, 'eta_unit' => 'week', 'features' => ['Pages'], 'active' => $active, 'sort_order' => 0,
        ]));
    }

    private function packagePayload(ServicePackage $package, array $overrides = []): array
    {
        return array_merge([
            'service_category_id' => $package->service_category_id, 'slug' => $package->slug, 'name' => $package->name,
            'tagline' => $package->tagline, 'price_min_myr' => 2500, 'price_max_myr' => 4000, 'unit' => 'per project',
            'duration_text' => '2 weeks', 'eta_value' => 2, 'eta_unit' => 'week', 'features' => ['Pages'],
            'sort_order' => 0, 'active' => $package->active,
        ], $overrides);
    }

    private function assertPurgedOnce(): void
    {
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->url() === self::URL
            && $r->method() === 'POST'
            && $r->hasHeader('Authorization', 'Bearer secret-token'));
    }

    // ── Services (categories) ───────────────────────────────────────────────

    public function test_hiding_a_live_service_purges_once(): void
    {
        $this->package($this->live);

        $this->patchJson("/api/v1/admin/service-categories/{$this->live->id}/active", ['active' => false], $this->adminHeaders())->assertOk();

        $this->assertPurgedOnce();
    }

    public function test_making_a_service_live_purges(): void
    {
        $this->patchJson("/api/v1/admin/service-categories/{$this->hidden->id}/active", ['active' => true], $this->adminHeaders())->assertOk();

        $this->assertPurgedOnce();
    }

    public function test_editing_a_live_service_purges(): void
    {
        $this->putJson("/api/v1/admin/service-categories/{$this->live->id}", [
            'slug' => 'web', 'name' => 'Websites & Landing Pages', 'icon' => 'i-lucide-globe', 'description' => 'Sites.', 'active' => true,
        ], $this->adminHeaders())->assertOk();

        $this->assertPurgedOnce();
    }

    public function test_editing_a_hidden_service_does_not_purge(): void
    {
        $this->putJson("/api/v1/admin/service-categories/{$this->hidden->id}", [
            'slug' => 'apps', 'name' => 'Mobile apps', 'icon' => 'i-lucide-box', 'description' => 'Apps.', 'active' => false,
        ], $this->adminHeaders())->assertOk();

        Http::assertNothingSent();
    }

    public function test_deleting_a_live_service_purges_and_a_hidden_one_does_not(): void
    {
        $this->deleteJson("/api/v1/admin/service-categories/{$this->hidden->id}", [], $this->adminHeaders())->assertOk();
        Http::assertNothingSent();

        $this->deleteJson("/api/v1/admin/service-categories/{$this->live->id}", [], $this->adminHeaders())->assertOk();
        $this->assertPurgedOnce();
    }

    // ── Packages ────────────────────────────────────────────────────────────

    public function test_hiding_a_live_package_purges(): void
    {
        $package = $this->package($this->live);

        $this->patchJson("/api/v1/admin/service-packages/{$package->id}/active", ['active' => false], $this->adminHeaders())->assertOk();
        $this->assertPurgedOnce();
    }

    public function test_switching_a_package_inside_a_hidden_service_does_not_purge(): void
    {
        $package = $this->package($this->hidden);

        $this->patchJson("/api/v1/admin/service-packages/{$package->id}/active", ['active' => false], $this->adminHeaders())->assertOk();
        $this->patchJson("/api/v1/admin/service-packages/{$package->id}/active", ['active' => true], $this->adminHeaders())->assertOk();

        Http::assertNothingSent();
    }

    public function test_moving_a_package_out_of_a_hidden_service_into_a_live_one_purges(): void
    {
        $package = $this->package($this->hidden);

        $this->putJson("/api/v1/admin/service-packages/{$package->id}", $this->packagePayload($package, [
            'service_category_id' => $this->live->id,
        ]), $this->adminHeaders())->assertOk();

        $this->assertPurgedOnce();
    }

    public function test_deleting_a_live_package_purges(): void
    {
        $package = $this->package($this->live);

        $this->deleteJson("/api/v1/admin/service-packages/{$package->id}", [], $this->adminHeaders())->assertOk();

        $this->assertPurgedOnce();
    }

    // ── The MCP connector only ever touches Hidden drafts ───────────────────

    public function test_connector_drafts_do_not_purge(): void
    {
        $headers = $this->connectorHeaders();

        $service = $this->postJson('/api/v1/connector/services', [
            'name' => 'Automation', 'icon' => 'i-lucide-bot', 'description' => 'Workflow automation for SMEs.',
        ], $headers)->assertCreated()->json('data.id');
        $this->putJson("/api/v1/connector/services/{$service}", ['name' => 'Automation & AI'], $headers)->assertOk();

        Http::assertNothingSent();
    }

    // ── What the /services estimator prices from ────────────────────────────

    public function test_an_add_on_change_purges(): void
    {
        $this->postJson('/api/v1/admin/service-addons', [
            'addon_key' => 'test_extra_language', 'label' => 'Extra language', 'amount_myr' => 900, 'active' => true,
        ], $this->adminHeaders())->assertCreated();

        $this->assertPurgedOnce();
    }

    public function test_a_pricing_config_change_purges(): void
    {
        // Pricing rows change from migrations / seeders (artisan), where the
        // after-response purge runs when the command terminates.
        PricingConfig::factory()->create();
        $this->app->terminate();

        $this->assertPurgedOnce();
    }
}
