<?php

namespace Tests\Feature\Pricing;

use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The /admin/services listing's Active switch flips `active` alone
 * (PATCH …/active) — every other field is left as it was, and the quote-builder
 * config cache is dropped like any other catalogue save.
 */
class AdminServiceActiveSwitchTest extends TestCase
{
    use RefreshDatabase;

    private ServiceCategory $web;

    private ServicePackage $business;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;
        $this->headers = ['Authorization' => "Bearer {$token}"];

        $this->web = ServiceCategory::forceCreate([
            'slug' => 'web', 'name' => 'Websites', 'icon' => 'i-lucide-globe', 'description' => 'Sites.', 'active' => false,
        ]);
        $this->business = ServicePackage::forceCreate([
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
            'active' => true,
        ]);
    }

    public function test_the_switch_shows_and_hides_a_category_without_touching_other_fields(): void
    {
        Cache::put('quote_builder_config_v1', ['stale' => true]);

        $this->patchJson("/api/v1/admin/service-categories/{$this->web->id}/active", ['active' => true], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.name', 'Websites');

        $this->assertTrue($this->web->fresh()->active);
        $this->assertSame('Sites.', $this->web->fresh()->description);
        $this->assertFalse(Cache::has('quote_builder_config_v1'));

        $this->patchJson("/api/v1/admin/service-categories/{$this->web->id}/active", ['active' => false], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $this->assertFalse($this->web->fresh()->active);
    }

    public function test_the_switch_hides_a_package_without_touching_other_fields(): void
    {
        $this->patchJson("/api/v1/admin/service-packages/{$this->business->id}/active", ['active' => false], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $fresh = $this->business->fresh();
        $this->assertFalse($fresh->active);
        $this->assertSame(['Pages'], $fresh->features);
        $this->assertSame('Multi-page site.', $fresh->tagline);
    }

    public function test_active_is_required_and_boolean(): void
    {
        $this->patchJson("/api/v1/admin/service-categories/{$this->web->id}/active", [], $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('active');

        $this->patchJson("/api/v1/admin/service-packages/{$this->business->id}/active", ['active' => 'yes'], $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('active');
    }

    public function test_the_switch_needs_an_admin_session(): void
    {
        $this->patchJson("/api/v1/admin/service-packages/{$this->business->id}/active", ['active' => false])
            ->assertUnauthorized();

        $this->assertTrue($this->business->fresh()->active);
    }
}
