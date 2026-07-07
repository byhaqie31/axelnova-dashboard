<?php

namespace Tests\Feature\Quoting;

use App\Models\PricingConfig;
use App\Services\Quoting\DocumentSeeder;
use App\Services\Quoting\MultiEstimateResult;
use App\Services\Quoting\PricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shared seeder behind the admin "Seed line items from scope" action and the
 * MCP connector. Pins the seeding math: base at range midpoint (rounded RM 50),
 * modifiers/add-ons at exact amount, rush as a single +20% line on the subtotal.
 */
class DocumentSeederTest extends TestCase
{
    use RefreshDatabase;

    private function engine(): PricingEngine
    {
        // Empty catalog (RefreshDatabase) → packageTagline() resolves to null; the
        // seeder only needs the rush multiplier from the config here.
        return new PricingEngine(new PricingConfig([
            'version' => 'test',
            'config' => ['rush_multiplier' => 1.20],
        ]));
    }

    private function estimate(array $breakdown): MultiEstimateResult
    {
        return new MultiEstimateResult(
            minMyr: 0,
            maxMyr: 0,
            etaValue: 2,
            etaUnit: 'week',
            breakdown: $breakdown,
        );
    }

    public function test_seeds_base_at_midpoint_and_modifiers_addons_at_exact_amount(): void
    {
        $seeded = (new DocumentSeeder($this->engine()))->seed($this->estimate([[
            'package_key' => 'web_business',
            'name' => 'Business Site',
            'min' => 5300,
            'max' => 7300,
            'eta_value' => 2,
            'eta_unit' => 'week',
            'lines' => [
                ['Base: Business Site', 3500, 5500],
                ['+cms', 1200, 1200],
                ['Addon: SEO setup', 600, 600],
            ],
        ]]), false);

        $items = $seeded['document']['items'];

        $this->assertSame('Business Site', $items[0]['title']);
        $this->assertSame(4500, $items[0]['rate']);   // midpoint(3500, 5500), rounded to 50
        $this->assertSame('cms', $items[1]['title']); // leading "+ " stripped
        $this->assertSame(1200.0, $items[1]['rate']); // exact fixed amount
        $this->assertSame('Addon: SEO setup', $items[2]['title']);
        $this->assertSame(600.0, $items[2]['rate']);  // exact

        $this->assertSame('standard', $seeded['document']['layout']);
        $this->assertSame(50, $seeded['document']['deposit_pct']);
        $this->assertCount(3, $seeded['document']['terms']);
        $this->assertStringContainsString('Business Site seeded at range midpoint RM 4,500', $seeded['assumptions'][0]);
    }

    public function test_rush_adds_a_single_uplift_line_on_the_subtotal(): void
    {
        $seeded = (new DocumentSeeder($this->engine()))->seed($this->estimate([[
            'package_key' => 'p',
            'name' => 'Pkg',
            'min' => 0, 'max' => 0, 'eta_value' => 1, 'eta_unit' => 'week',
            'lines' => [['Base: Pkg', 4000, 6000]],
        ]]), true);

        $items = $seeded['document']['items'];

        $this->assertSame(5000, $items[0]['rate']);              // midpoint(4000, 6000)
        $this->assertSame('Rush delivery (+20%)', $items[1]['title']);
        $this->assertSame(1000, $items[1]['rate']);             // round50(5000 × 0.20)
        $this->assertCount(2, $items);
    }

    public function test_seeds_one_base_line_per_package(): void
    {
        $seeded = (new DocumentSeeder($this->engine()))->seed($this->estimate([
            ['package_key' => 'a', 'name' => 'A', 'lines' => [['Base: A', 1000, 2000]]],
            ['package_key' => 'b', 'name' => 'B', 'lines' => [['Base: B', 3000, 3000]]],
        ]), false);

        $items = $seeded['document']['items'];
        $this->assertCount(2, $items);
        $this->assertSame('A', $items[0]['title']);
        $this->assertSame(1500, $items[0]['rate']);
        $this->assertSame('B', $items[1]['title']);
        $this->assertSame(3000, $items[1]['rate']);
        $this->assertCount(2, $seeded['assumptions']);
    }

    public function test_bespoke_extras_become_document_lines(): void
    {
        $seeded = (new DocumentSeeder($this->engine()))->seed($this->estimate([]), false, [
            ['label' => 'Custom booking engine', 'description' => 'Bespoke flow', 'amount_myr' => 12000],
            ['label' => 'Data migration', 'amount_myr' => 3000],
        ]);

        $items = $seeded['document']['items'];
        $this->assertCount(2, $items);
        $this->assertSame('Custom booking engine', $items[0]['title']);
        $this->assertSame(12000.0, $items[0]['rate']);
        $this->assertSame([], $seeded['assumptions']);
    }

    public function test_has_content_detects_admin_authored_documents(): void
    {
        $this->assertFalse(DocumentSeeder::hasContent(null));
        $this->assertFalse(DocumentSeeder::hasContent([]));
        $this->assertFalse(DocumentSeeder::hasContent(['items' => []]));
        $this->assertTrue(DocumentSeeder::hasContent(['items' => [['title' => 'x']]]));
        $this->assertTrue(DocumentSeeder::hasContent(['line_items' => [['label' => 'x']]]));
        $this->assertTrue(DocumentSeeder::hasContent(['layout' => 'detailed', 'payload' => ['sections' => [['total' => 1]]]]));
    }
}
