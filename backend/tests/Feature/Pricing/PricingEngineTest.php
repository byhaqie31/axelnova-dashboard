<?php

namespace Tests\Feature\Pricing;

use App\Models\PricingConfig;
use App\Models\ServiceAddon;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\ServiceScopeField;
use App\Services\Quoting\PricingEngine;
use App\Services\Quoting\QuoteRequestInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Pins the pricing formula. The TS port (frontend usePricingEngine.ts) must
 * produce the same numbers — if a test here changes, the port changes with it.
 *
 * Uses test-only package/addon keys so the rows the service_addons migration
 * seeds can never collide with the JSON fixtures.
 */
class PricingEngineTest extends TestCase
{
    use RefreshDatabase;

    private function engine(array $config = []): PricingEngine
    {
        $defaults = [
            'currency' => 'MYR',
            'rush_multiplier' => 1.20,
            'base_packages' => [
                'test_landing' => ['min' => 1500, 'max' => 2500, 'eta_value' => 4, 'eta_unit' => 'week'],
            ],
            'modifiers' => [],
            'addons' => [],
        ];

        return new PricingEngine(new PricingConfig([
            'version' => 'test',
            'config' => array_replace_recursive($defaults, $config),
        ]));
    }

    private function input(array $overrides = []): QuoteRequestInput
    {
        return new QuoteRequestInput(
            name: 'Test Client',
            email: 'client@example.com',
            phone: '0123456789',
            company: null,
            packageKey: $overrides['packageKey'] ?? 'test_landing',
            modifiers: $overrides['modifiers'] ?? [],
            addonKeys: $overrides['addonKeys'] ?? [],
            rush: $overrides['rush'] ?? false,
            scopeValues: $overrides['scopeValues'] ?? [],
        );
    }

    public function test_base_package_price_and_eta(): void
    {
        $result = $this->engine()->calculate($this->input());

        $this->assertSame(1500, $result->minMyr);
        $this->assertSame(2500, $result->maxMyr);
        $this->assertSame(4, $result->etaValue);
        $this->assertSame('week', $result->etaUnit);
    }

    public function test_totals_round_to_the_nearest_50(): void
    {
        $engine = $this->engine([
            'base_packages' => ['test_landing' => ['min' => 1520, 'max' => 2480]],
        ]);

        $result = $engine->calculate($this->input());

        $this->assertSame(1500, $result->minMyr);
        $this->assertSame(2500, $result->maxMyr);
    }

    public function test_boolean_modifier_adds_its_amount(): void
    {
        $engine = $this->engine([
            'modifiers' => ['test_cms' => ['amount' => 800, 'applies_to' => 'all']],
        ]);

        $result = $engine->calculate($this->input(['modifiers' => ['test_cms' => true]]));

        $this->assertSame(2300, $result->minMyr);
        $this->assertSame(3300, $result->maxMyr);
    }

    public function test_threshold_modifier_charges_only_above_the_free_allowance(): void
    {
        $engine = $this->engine([
            'modifiers' => ['test_pages' => ['amount' => 150, 'applies_after' => 5, 'applies_to' => 'all']],
        ]);

        $result = $engine->calculate($this->input(['modifiers' => ['test_pages' => 8]]));

        // 3 pages over the allowance × 150 = 450
        $this->assertSame(1950, $result->minMyr);
        $this->assertSame(2950, $result->maxMyr);
    }

    public function test_modifier_scoped_to_another_package_is_ignored(): void
    {
        $engine = $this->engine([
            'modifiers' => ['test_cms' => ['amount' => 800, 'applies_to' => ['some_other_package']]],
        ]);

        $result = $engine->calculate($this->input(['modifiers' => ['test_cms' => true]]));

        $this->assertSame(1500, $result->minMyr);
    }

    public function test_addons_add_their_amount(): void
    {
        $engine = $this->engine([
            'addons' => ['test_addon' => ['amount' => 350, 'label' => 'Test Addon']],
        ]);

        $result = $engine->calculate($this->input(['addonKeys' => ['test_addon', 'unknown_addon']]));

        $this->assertSame(1850, $result->minMyr);
        $this->assertSame(2850, $result->maxMyr);
    }

    public function test_rush_multiplies_price_and_shortens_week_etas(): void
    {
        $result = $this->engine()->calculate($this->input(['rush' => true]));

        // 1500 × 1.2 = 1800; 2500 × 1.2 = 3000; eta floor(4 × 0.7) = 2
        $this->assertSame(1800, $result->minMyr);
        $this->assertSame(3000, $result->maxMyr);
        $this->assertSame(2, $result->etaValue);
    }

    public function test_rush_leaves_day_etas_untouched(): void
    {
        $engine = $this->engine([
            'base_packages' => ['test_landing' => ['min' => 1000, 'max' => 2000, 'eta_value' => 3, 'eta_unit' => 'day']],
        ]);

        $result = $engine->calculate($this->input(['rush' => true]));

        $this->assertSame(1200, $result->minMyr);
        $this->assertSame(3, $result->etaValue);
        $this->assertSame('day', $result->etaUnit);
    }

    public function test_unknown_package_key_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine()->calculate($this->input(['packageKey' => 'nope']));
    }

    public function test_db_service_package_overrides_the_json_entry(): void
    {
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
            'name' => 'Landing (DB)',
            'tagline' => 'DB-managed',
            'price_min_myr' => 3000,
            'price_max_myr' => 4000,
            'unit' => 'project',
            'duration_text' => '2 weeks',
            'features' => [],
            'quote_key' => ['package' => 'test_landing'],
            'eta_value' => 2,
            'eta_unit' => 'week',
            'active' => true,
        ]);

        $result = $this->engine()->calculate($this->input());

        $this->assertSame(3000, $result->minMyr);
        $this->assertSame(4000, $result->maxMyr);
        $this->assertSame(2, $result->etaValue);
    }

    public function test_db_addon_claims_its_key_and_inactive_rows_hide_it(): void
    {
        $engine = fn () => $this->engine([
            'addons' => ['test_addon' => ['amount' => 350, 'label' => 'JSON Addon']],
        ]);

        ServiceAddon::forceCreate([
            'addon_key' => 'test_addon',
            'label' => 'DB Addon',
            'amount_myr' => 500,
            'active' => true,
        ]);
        $result = $engine()->calculate($this->input(['addonKeys' => ['test_addon']]));
        $this->assertSame(2000, $result->minMyr); // 1500 + 500 (DB wins over JSON's 350)

        ServiceAddon::where('addon_key', 'test_addon')->update(['active' => false]);
        $result = $engine()->calculate($this->input(['addonKeys' => ['test_addon']]));
        $this->assertSame(1500, $result->minMyr); // inactive row hides the key entirely
    }

    public function test_slider_scope_field_charges_above_its_free_threshold(): void
    {
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
            'name' => 'Landing (DB)',
            'tagline' => 'DB-managed',
            'price_min_myr' => 1500,
            'price_max_myr' => 2500,
            'unit' => 'project',
            'duration_text' => '2 weeks',
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

        $result = $this->engine()->calculate($this->input(['scopeValues' => ['test_pages' => 8]]));

        // 3 pages over × 100 = 300
        $this->assertSame(1800, $result->minMyr);
        $this->assertSame(2800, $result->maxMyr);
    }

    public function test_multi_package_sums_prices_and_takes_the_longest_eta(): void
    {
        $engine = $this->engine([
            'base_packages' => [
                'pkg_two_weeks' => ['min' => 1000, 'max' => 2000, 'eta_value' => 2, 'eta_unit' => 'week'],
                'pkg_ten_days' => ['min' => 500, 'max' => 800, 'eta_value' => 10, 'eta_unit' => 'day'],
            ],
        ]);

        $result = $engine->calculateMulti([
            ['package_key' => 'pkg_two_weeks'],
            ['package_key' => 'pkg_ten_days'],
        ], false);

        $this->assertSame(1500, $result->minMyr);   // 1000 + 500
        $this->assertSame(2800, $result->maxMyr);   // 2000 + 800
        // 2 weeks (14d) > 10 days → the winner keeps its own value + unit.
        $this->assertSame(2, $result->etaValue);
        $this->assertSame('week', $result->etaUnit);
        // Breakdown is grouped per package, in input order.
        $this->assertCount(2, $result->breakdown);
        $this->assertSame('pkg_two_weeks', $result->breakdown[0]['package_key']);
        $this->assertSame(1000, $result->breakdown[0]['min']);
    }

    public function test_multi_package_eta_winner_compares_across_units(): void
    {
        $engine = $this->engine([
            'base_packages' => [
                'pkg_two_weeks' => ['min' => 1000, 'max' => 2000, 'eta_value' => 2, 'eta_unit' => 'week'],
                'pkg_thirty_days' => ['min' => 100, 'max' => 100, 'eta_value' => 30, 'eta_unit' => 'day'],
            ],
        ]);

        $result = $engine->calculateMulti([
            ['package_key' => 'pkg_two_weeks'],
            ['package_key' => 'pkg_thirty_days'],
        ], false);

        // 30 days > 2 weeks (14d) → the day package's ETA wins despite the unit gap.
        $this->assertSame(30, $result->etaValue);
        $this->assertSame('day', $result->etaUnit);
    }

    public function test_multi_package_with_no_packages_is_zero_with_no_eta_sentinel(): void
    {
        $result = $this->engine()->calculateMulti([], false);

        $this->assertSame(0, $result->minMyr);
        $this->assertSame(0, $result->maxMyr);
        $this->assertSame(0, $result->etaValue);
        $this->assertSame('week', $result->etaUnit);
        $this->assertSame([], $result->breakdown);
    }

    public function test_multi_package_rush_multiplies_each_and_leaves_lines_pre_rush(): void
    {
        $engine = $this->engine([
            'base_packages' => [
                'pkg_a' => ['min' => 1000, 'max' => 2000, 'eta_value' => 2, 'eta_unit' => 'week'],
            ],
        ]);

        $result = $engine->calculateMulti([['package_key' => 'pkg_a']], true);

        // 1000 × 1.2 = 1200; 2000 × 1.2 = 2400; eta floor(2 × 0.7) = 1.
        $this->assertSame(1200, $result->minMyr);
        $this->assertSame(2400, $result->maxMyr);
        $this->assertSame(1, $result->etaValue);
        // The seeder relies on the group lines being PRE-rush (base line un-multiplied).
        $this->assertSame(1000.0, $result->breakdown[0]['lines'][0][1]);
        $this->assertSame(2000.0, $result->breakdown[0]['lines'][0][2]);
    }
}
