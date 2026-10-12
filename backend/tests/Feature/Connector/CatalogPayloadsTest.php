<?php

namespace Tests\Feature\Connector;

use App\Models\BlogPost;
use App\Models\Project;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Service + package payloads after the 2026-10 catalog clean-up, across the
 * public API, the admin API and the MCP connector:
 *  - no likes on services or packages (projects keep theirs);
 *  - `public_url` points at the public site, never the internal admin host,
 *    while `admin_url` stays on the admin host.
 */
class CatalogPayloadsTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = 'https://admin.axelnovaventures.test';

    private const SITE = 'https://axelnovaventures.test';

    private User $founder;

    private ServiceCategory $service;

    private ServicePackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.frontend.url' => self::ADMIN,
            'services.frontend.public_url' => self::SITE,
        ]);

        $this->founder = User::factory()->founder()->create();
        $this->service = ServiceCategory::forceCreate([
            'slug' => 'web-presence', 'name' => 'Web Presence', 'icon' => 'i-lucide-globe',
            'description' => 'Sites.', 'sort_order' => 0, 'active' => true,
        ]);
        $this->package = ServicePackage::forceCreate([
            'service_category_id' => $this->service->id,
            'slug' => 'web-business',
            'name' => 'Business Site',
            'tagline' => 'A proper web presence.',
            'price_min_myr' => 5000,
            'price_max_myr' => 8000,
            'unit' => 'per project',
            'duration_text' => '3-4 weeks',
            'eta_value' => 4,
            'eta_unit' => 'week',
            'features' => ['Up to 5 pages'],
            'quote_key' => ['category' => 'web-presence', 'package' => 'web_business'],
            'sort_order' => 0,
            'active' => true,
        ]);
    }

    private function connector(): array
    {
        $token = $this->founder->createToken('mcp-connector', ['connector:read'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function cockpit(): array
    {
        $token = $this->founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_package_and_service_payloads_carry_no_likes_count(): void
    {
        $public = $this->getJson('/api/v1/services')->assertOk();
        $this->assertArrayNotHasKey('likes_count', $public->json('data.0.packages.0'));

        $this->assertArrayNotHasKey('likes_count', $this->getJson('/api/v1/admin/service-packages', $this->cockpit())->assertOk()->json('data.0'));
        $this->assertArrayNotHasKey('likes_count', $this->getJson("/api/v1/admin/service-packages/{$this->package->id}", $this->cockpit())->assertOk()->json('data'));
        $this->assertArrayNotHasKey('likes_count', $this->getJson("/api/v1/admin/service-categories/{$this->service->id}", $this->cockpit())->assertOk()->json('data.packages.0'));
    }

    public function test_connector_package_and_service_records_carry_no_likes_count(): void
    {
        $package = $this->getJson("/api/v1/connector/packages/{$this->package->id}", $this->connector())->assertOk()->json('data');
        $this->assertArrayNotHasKey('likes_count', $package);
        $service = $this->getJson("/api/v1/connector/services/{$this->service->id}", $this->connector())->assertOk()->json('data');
        $this->assertArrayNotHasKey('likes_count', $service);
        $this->assertArrayNotHasKey('likes_count', $service['packages'][0]);
    }

    public function test_packages_can_no_longer_be_liked_but_projects_can(): void
    {
        $this->postJson("/api/v1/likes/service_package/{$this->package->id}", ['cookie_id' => 'c-1'])->assertNotFound();

        $project = Project::create([
            'slug' => 'house-of-parfum', 'name' => 'House of Parfum', 'description' => 'Perfume e-commerce.',
            'long_description' => 'Long story.', 'status' => 'wip', 'tags' => [], 'stack' => [], 'sort_order' => 0, 'active' => true,
        ]);
        $this->postJson("/api/v1/likes/project/{$project->id}", ['cookie_id' => 'c-1'])
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_connector_public_urls_use_the_public_site_and_admin_urls_the_admin_host(): void
    {
        $this->getJson("/api/v1/connector/packages/{$this->package->id}", $this->connector())
            ->assertOk()
            ->assertJsonPath('data.public_url', self::SITE.'/services')
            ->assertJsonPath('data.admin_url', self::ADMIN."/admin/services/packages/{$this->package->id}");

        $this->getJson("/api/v1/connector/services/{$this->service->id}", $this->connector())
            ->assertOk()
            ->assertJsonPath('data.public_url', self::SITE.'/services')
            ->assertJsonPath('data.admin_url', self::ADMIN."/admin/services/categories/{$this->service->id}");

        $project = Project::create([
            'slug' => 'house-of-parfum', 'name' => 'House of Parfum', 'description' => 'Perfume e-commerce.',
            'long_description' => 'Long story.', 'status' => 'wip', 'tags' => [], 'stack' => [], 'sort_order' => 0, 'active' => true,
        ]);
        $this->getJson("/api/v1/connector/projects/{$project->id}", $this->connector())
            ->assertOk()
            ->assertJsonPath('data.public_url', self::SITE.'/projects/house-of-parfum')
            ->assertJsonPath('data.admin_url', self::ADMIN."/admin/projects/{$project->id}");

        $post = BlogPost::factory()->published()->create();
        $this->getJson("/api/v1/connector/blog/posts/{$post->id}", $this->connector())
            ->assertOk()
            ->assertJsonPath('data.public_url', self::SITE."/blog/{$post->slug}")
            ->assertJsonPath('data.admin_url', self::ADMIN."/admin/blog/{$post->id}");
    }
}
