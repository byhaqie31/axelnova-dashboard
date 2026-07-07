<?php

namespace Tests\Unit\Quoting;

use App\Services\Quoting\FormPayloadNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * The reader seam behind "one write shape, three writers, one renderer". Pins the
 * normalisation of every historical form_payload shape (old funnel flat, current
 * admin scope_values, MCP connector, and the new multi-package) into the canonical
 * packages[] view. Pure — no DB, no engine.
 */
class FormPayloadNormalizerTest extends TestCase
{
    public function test_new_multi_package_shape_passes_through(): void
    {
        $out = FormPayloadNormalizer::normalize([
            'packages' => [
                ['package_key' => 'web_business', 'service_package_id' => 6, 'modifiers' => ['cms' => true], 'addon_keys' => ['seo']],
                ['package_key' => 'dash_starter', 'scope_values' => ['pages' => 8]],
            ],
            'rush' => true,
            'source_meta' => ['created_via' => 'admin'],
        ]);

        $this->assertCount(2, $out['packages']);
        $this->assertSame('web_business', $out['packages'][0]['package_key']);
        $this->assertSame(6, $out['packages'][0]['service_package_id']);
        $this->assertSame(['cms' => true], $out['packages'][0]['modifiers']);
        $this->assertSame(['seo'], $out['packages'][0]['addon_keys']);
        $this->assertSame(['pages' => 8], $out['packages'][1]['scope_values']);
        $this->assertNull($out['packages'][1]['service_package_id']);
        $this->assertTrue($out['rush']);
        $this->assertSame('admin', $out['source_meta']['created_via']);
    }

    public function test_legacy_funnel_flat_shape_becomes_one_package(): void
    {
        // The old public-funnel payload: flat scope keys + a `modifiers` sub-map,
        // no category_key, no scope_values, no packages[].
        $out = FormPayloadNormalizer::normalize([
            'package_key' => 'web_business',
            'cms' => true,
            'pages' => 8,
            'modifiers' => ['real_time_features' => true],
            'rush' => false,
        ]);

        $this->assertCount(1, $out['packages']);
        $this->assertSame('web_business', $out['packages'][0]['package_key']);
        $this->assertSame(['real_time_features' => true], $out['packages'][0]['modifiers']);
        $this->assertSame([], $out['packages'][0]['scope_values']);
        $this->assertNull($out['packages'][0]['service_package_id']);
        $this->assertNull($out['source_meta']['created_via']);
    }

    public function test_current_scope_values_admin_shape_becomes_one_package(): void
    {
        $out = FormPayloadNormalizer::normalize([
            'package_key' => 'web_business',
            'scope_values' => ['pages' => 6, 'cms' => true],
            'addon_keys' => ['seo'],
            'rush' => true,
            'modifiers' => [],
        ]);

        $this->assertCount(1, $out['packages']);
        $this->assertSame(['pages' => 6, 'cms' => true], $out['packages'][0]['scope_values']);
        $this->assertSame(['seo'], $out['packages'][0]['addon_keys']);
        $this->assertTrue($out['rush']);
    }

    public function test_connector_shape_surfaces_created_via_from_document(): void
    {
        $out = FormPayloadNormalizer::normalize(
            [
                'package_key' => 'web_business',
                'modifiers' => [],
                'scope_values' => ['pages' => 8],
                'addon_keys' => [],
                'rush' => false,
                'created_via' => 'mcp_connector',
            ],
            'web_business',
            ['created_via' => 'mcp_connector'],
        );

        $this->assertSame('mcp_connector', $out['source_meta']['created_via']);
        $this->assertCount(1, $out['packages']);
    }

    public function test_source_meta_created_via_wins_over_legacy_locations(): void
    {
        $out = FormPayloadNormalizer::normalize(
            ['package_key' => 'web_business', 'source_meta' => ['created_via' => 'admin'], 'created_via' => 'mcp_connector'],
            null,
            ['created_via' => 'mcp_connector'],
        );

        $this->assertSame('admin', $out['source_meta']['created_via']);
    }

    public function test_falls_back_to_the_scalar_package_key_column(): void
    {
        // A minimal/legacy row whose form_payload omits package_key — use the column.
        $out = FormPayloadNormalizer::normalize(['scope_values' => []], 'dash_starter');

        $this->assertCount(1, $out['packages']);
        $this->assertSame('dash_starter', $out['packages'][0]['package_key']);
    }

    public function test_bespoke_row_with_no_package_yields_no_packages(): void
    {
        $out = FormPayloadNormalizer::normalize(['rush' => false], null);

        $this->assertSame([], $out['packages']);
        $this->assertFalse($out['rush']);
    }

    public function test_flatten_breakdown_handles_grouped_and_flat(): void
    {
        $grouped = [
            ['package_key' => 'a', 'lines' => [['Base: A', 1000, 2000], ['+cms', 500, 500]]],
            ['package_key' => 'b', 'lines' => [['Base: B', 300, 300]]],
        ];
        $flat = FormPayloadNormalizer::flattenBreakdown($grouped);
        $this->assertCount(3, $flat);
        $this->assertSame('Base: A', $flat[0][0]);
        $this->assertSame('Base: B', $flat[2][0]);

        // A legacy flat tuple list passes through unchanged.
        $legacy = [['Base: X', 100, 200], ['Rush', 0, 0]];
        $this->assertSame($legacy, FormPayloadNormalizer::flattenBreakdown($legacy));

        $this->assertSame([], FormPayloadNormalizer::flattenBreakdown([]));
        $this->assertSame([], FormPayloadNormalizer::flattenBreakdown(null));
    }

    public function test_normalize_wraps_legacy_flat_breakdown_into_one_group(): void
    {
        $out = FormPayloadNormalizer::normalize([
            'package_key' => 'web_business',
            'breakdown' => [['Base: Business', 3500, 5500], ['+cms', 1200, 1200]],
        ]);

        $this->assertCount(1, $out['breakdown']);
        $this->assertSame('web_business', $out['breakdown'][0]['package_key']);
        $this->assertCount(2, $out['breakdown'][0]['lines']);
    }
}
