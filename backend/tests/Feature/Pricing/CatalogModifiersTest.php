<?php

namespace Tests\Feature\Pricing;

use App\Models\PricingConfig;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Services\Connector\ConnectorCatalog;
use App\Services\Quoting\PricingEngine;
use App\Services\Quoting\QuoteRequestInput;
use Database\Seeders\PricingConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The package modifiers after the 2026-10 catalog rewrite, as shipped by the
 * 2026_10_12_100001 data migration on top of the legacy pricing JSON prod runs
 * (PricingConfigSeeder's shape): Stripe / FPX only on build packages, extra_page
 * RM500 on web_business and RM800 on web_premium, nothing else changed.
 */
class CatalogModifiersTest extends TestCase
{
    use RefreshDatabase;

    /** category slug → quotable package keys (the live catalog). */
    private const CATALOG = [
        'digital-marketing' => ['single_asset', 'campaign_pack', 'event_activation', 'brand_refresh', 'content_retainer'],
        'web-presence' => ['web_starter', 'web_business', 'web_premium'],
        'ui-ux-frontend' => ['ux_audit', 'design_build', 'design_system'],
        'booking-portal' => ['booking_lite', 'booking_pro'],
        'ecommerce' => ['store_starter', 'store_pro'],
        'admin-portal' => ['admin_starter', 'admin_business'],
    ];

    private const NO_PAYMENTS = [
        'single_asset', 'campaign_pack', 'event_activation', 'brand_refresh',
        'content_retainer', 'ux_audit', 'design_system',
    ];

    private const PAYMENTS = [
        'web_starter', 'web_business', 'web_premium', 'booking_lite', 'booking_pro',
        'store_starter', 'store_pro', 'admin_starter', 'admin_business', 'design_build',
    ];

    private array $before;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PricingConfigSeeder::class);
        $this->before = PricingConfig::where('active', true)->firstOrFail()->config;

        $order = 0;
        foreach (self::CATALOG as $slug => $keys) {
            $category = ServiceCategory::forceCreate([
                'slug' => $slug, 'name' => ucfirst($slug), 'icon' => 'i-lucide-box',
                'description' => 'Test service.', 'sort_order' => $order++, 'active' => true,
            ]);
            foreach ($keys as $i => $key) {
                ServicePackage::forceCreate([
                    'service_category_id' => $category->id,
                    'slug' => str_replace('_', '-', $key),
                    'name' => ucfirst(str_replace('_', ' ', $key)),
                    'tagline' => 'Test package.',
                    'price_min_myr' => 5000,
                    'price_max_myr' => 8000,
                    'unit' => 'per project',
                    'duration_text' => '4 weeks',
                    'eta_value' => 4,
                    'eta_unit' => 'week',
                    'features' => ['A feature'],
                    'quote_key' => ['category' => $slug, 'package' => $key],
                    'sort_order' => $i,
                    'active' => true,
                ]);
            }
        }

        $this->migrate();
    }

    private function migrate(): void
    {
        foreach (['2026_10_12_100001_scope_payment_modifiers_and_extra_page_prices', '2026_10_12_100002_label_pricing_modifiers'] as $name) {
            $migration = require database_path("migrations/{$name}.php");
            $migration->up();
        }
        Cache::flush();
    }

    private function catalog(): ConnectorCatalog
    {
        return new ConnectorCatalog(PricingEngine::active()->configForFrontend());
    }

    private function price(string $packageKey, array $modifiers): array
    {
        $result = PricingEngine::active()->calculate(new QuoteRequestInput(
            name: '', email: '', phone: '', company: null,
            packageKey: $packageKey, modifiers: $modifiers, addonKeys: [], rush: false,
        ));

        return [$result->minMyr, $result->maxMyr];
    }

    public function test_payment_modifiers_are_removed_from_the_creative_audit_and_design_system_packages(): void
    {
        $catalog = $this->catalog();

        foreach (self::NO_PAYMENTS as $key) {
            $modifiers = $catalog->validModifierKeys($key);
            $this->assertNotContains('stripe_integration', $modifiers, $key);
            $this->assertNotContains('fpx_integration', $modifiers, $key);
        }
    }

    public function test_payment_modifiers_stay_on_every_build_package(): void
    {
        $catalog = $this->catalog();

        foreach (self::PAYMENTS as $key) {
            $modifiers = $catalog->validModifierKeys($key);
            $this->assertContains('stripe_integration', $modifiers, $key);
            $this->assertContains('fpx_integration', $modifiers, $key);
        }
    }

    public function test_the_legacy_json_packages_keep_their_payment_modifiers(): void
    {
        $applies = PricingConfig::where('active', true)->firstOrFail()->config['modifiers']['stripe_integration']['applies_to'];

        foreach (array_keys($this->before['base_packages']) as $legacyKey) {
            $this->assertContains($legacyKey, $applies, $legacyKey);
        }
    }

    public function test_event_activation_keeps_extra_language_as_its_only_modifier(): void
    {
        $this->assertSame(['extra_language'], $this->catalog()->validModifierKeys('event_activation'));
    }

    public function test_extra_page_is_rm500_on_business_and_rm800_on_premium(): void
    {
        // 7 pages = 2 above the free 5.
        $this->assertSame([5000 + 1000, 8000 + 1000], $this->price('web_business', ['extra_page' => 7]));
        $this->assertSame([5000 + 1600, 8000 + 1600], $this->price('web_premium', ['extra_page' => 7]));
        $this->assertSame([5000, 8000], $this->price('web_business', ['extra_page' => 5]));

        $catalog = $this->catalog();
        $this->assertSame(500, $catalog->modifiersForPackage('web_business')['extra_page']['pricing']['amount_myr']);
        $this->assertSame(800, $catalog->modifiersForPackage('web_premium')['extra_page']['pricing']['amount_myr']);
        $this->assertSame(5, $catalog->modifiersForPackage('web_premium')['extra_page']['pricing']['free_threshold']);
    }

    public function test_payment_modifier_amounts_are_unchanged(): void
    {
        $this->assertSame([5000 + 1800, 8000 + 1800], $this->price('booking_lite', ['stripe_integration' => true]));
        $this->assertSame([5000 + 2200, 8000 + 2200], $this->price('store_pro', ['fpx_integration' => true]));
        // Sent to a package that no longer takes it: ignored, not charged.
        $this->assertSame([5000, 8000], $this->price('campaign_pack', ['stripe_integration' => true]));
    }

    public function test_base_prices_addons_and_rush_are_untouched(): void
    {
        $after = PricingConfig::where('active', true)->firstOrFail()->config;

        $this->assertSame($this->before['base_packages'], $after['base_packages']);
        $this->assertSame($this->before['addons'], $after['addons']);
        $this->assertEquals($this->before['rush_multiplier'], $after['rush_multiplier']);
        // Every other modifier keeps its pricing; only display labels are added.
        $pricing = fn (array $modifiers) => array_map(
            fn (array $def) => array_diff_key($def, array_flip(['label', 'unit'])),
            array_diff_key($modifiers, array_flip(['stripe_integration', 'fpx_integration', 'extra_page'])),
        );
        $this->assertSame($pricing($this->before['modifiers']), $pricing($after['modifiers']));
        $this->assertSame($this->before['modifiers']['extra_page']['amount'], $after['modifiers']['extra_page']['amount']);
    }

    public function test_modifiers_carry_display_labels_for_the_estimator_and_connector(): void
    {
        $modifiers = PricingConfig::where('active', true)->firstOrFail()->config['modifiers'];
        $this->assertSame('Stripe card payments', $modifiers['stripe_integration']['label']);
        $this->assertEquals(['label' => 'Number of pages', 'unit' => 'page'], array_intersect_key($modifiers['extra_page'], array_flip(['label', 'unit'])));

        $byKey = collect($this->catalog()->toArray()['packages'])->keyBy('key');
        $labels = collect($byKey['booking_lite']['modifiers'])->pluck('label', 'key')->all();
        $this->assertSame(['extra_language' => 'Extra language', 'fpx_integration' => 'FPX online banking', 'stripe_integration' => 'Stripe card payments'], collect($labels)->sortKeys()->all());
    }

    public function test_it_ships_as_a_new_active_version_and_is_idempotent(): void
    {
        $this->migrate();

        $this->assertSame(1, PricingConfig::where('active', true)->count());
        $this->assertSame('2026.10.12', PricingConfig::where('active', true)->value('version'));
        $this->assertSame(2, PricingConfig::count());
    }
}
