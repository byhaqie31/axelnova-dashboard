<?php

namespace Tests\Feature\Orders;

use App\Models\Client;
use App\Models\Order;
use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order detail's "Scope snapshot" (and the quotation detail's spec grid)
 * must read form_payload through the normalizer, never raw. A canonical
 * connector/multi-package payload carries `request` and `source_meta` objects
 * that the old raw passthrough rendered as "[object Object]"; the curated
 * scope groups exclude them structurally. Legacy flat funnel rows keep their
 * human scope fields; detailed/bespoke rows (scope lives in the document)
 * yield no groups at all.
 */
class OrderScopeSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function orderFor(array $quotationAttrs): Order
    {
        PricingConfig::factory()->create();
        $client = Client::factory()->create();
        $quotation = Quotation::factory()->create(['client_id' => $client->id] + $quotationAttrs);

        return Order::factory()->create([
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
        ]);
    }

    public function test_canonical_multi_package_payload_yields_curated_groups(): void
    {
        $order = $this->orderFor([
            'package_key' => 'test_landing',
            'form_payload' => [
                'request' => ['client' => ['name' => 'X'], 'notes' => 'audit copy'],
                'packages' => [[
                    'package_key' => 'test_landing',
                    'service_package_id' => null,
                    'scope_values' => ['extra_page' => 4, 'payment_method' => 'fpx'],
                    'modifiers' => ['cms' => true],
                    'addon_keys' => ['seo'],
                ]],
                'rush' => false,
                'breakdown' => [],
                'source_meta' => ['created_via' => 'mcp_connector'],
            ],
        ]);

        $body = $this->getJson("/api/v1/admin/orders/{$order->id}", $this->adminHeaders())
            ->assertOk()
            ->json('data');

        $scope = $body['quotation_scope'];
        $this->assertIsList($scope);
        $this->assertCount(1, $scope);
        $this->assertSame('test_landing', $scope[0]['package_key']);
        $this->assertSame('Test Landing', $scope[0]['label']);
        $this->assertSame(
            ['extra_page' => 4, 'payment_method' => 'fpx', 'cms' => true],
            $scope[0]['scope'],
        );

        // The raw audit objects must never reach the scope display.
        $this->assertStringNotContainsString('"request"', json_encode($scope));
        $this->assertStringNotContainsString('source_meta', json_encode($scope));

        // Every scope value is a scalar — nothing can stringify to [object Object].
        foreach ($scope[0]['scope'] as $value) {
            $this->assertTrue(is_scalar($value), 'scope values must be scalar');
        }
    }

    public function test_legacy_flat_payload_lifts_human_scope_fields(): void
    {
        $order = $this->orderFor([
            'package_key' => 'test_landing',
            'form_payload' => [
                'package_key' => 'test_landing',
                'pages' => 8,
                'cms' => true,
                'languages' => ['en', 'ms'],
                'core_features' => ['Booking', 'Blog'],
                'rush' => true,
                'breakdown' => [['Base', 1500, 2500]],
                'name' => 'Legacy Person',
                'email' => 'legacy@example.com',
            ],
        ]);

        $scope = $this->getJson("/api/v1/admin/orders/{$order->id}", $this->adminHeaders())
            ->assertOk()
            ->json('data.quotation_scope');

        $this->assertCount(1, $scope);
        $fields = $scope[0]['scope'];
        $this->assertSame(8, $fields['pages']);
        $this->assertTrue($fields['cms']);
        $this->assertSame(['en', 'ms'], $fields['languages']);
        $this->assertSame(['Booking', 'Blog'], $fields['core_features']);
        // Pricing controls and contact identity never surface as scope.
        $this->assertArrayNotHasKey('rush', $fields);
        $this->assertArrayNotHasKey('breakdown', $fields);
        $this->assertArrayNotHasKey('name', $fields);
        $this->assertArrayNotHasKey('email', $fields);
    }

    public function test_detailed_document_quotation_yields_no_scope_groups(): void
    {
        $order = $this->orderFor([
            'package_key' => null,
            'form_payload' => [
                'request' => ['detailed' => ['sections' => []]],
                'packages' => [],
                'rush' => false,
                'breakdown' => [],
                'source_meta' => ['created_via' => 'mcp_connector'],
            ],
            'document' => [
                'layout' => 'detailed',
                'deposit_pct' => 20,
                'payload' => ['sections' => [['title' => 'Scope', 'rows' => [], 'total' => 1000.0]]],
            ],
        ]);

        $this->getJson("/api/v1/admin/orders/{$order->id}", $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.quotation_scope', []);
    }

    public function test_quotation_detail_exposes_the_same_scope_display(): void
    {
        PricingConfig::factory()->create();
        $quotation = Quotation::factory()->create([
            'package_key' => 'test_landing',
            'form_payload' => [
                'request' => ['x' => ['y' => 1]],
                'packages' => [[
                    'package_key' => 'test_landing',
                    'scope_values' => ['extra_page' => 2],
                    'modifiers' => [],
                    'addon_keys' => [],
                ]],
                'rush' => false,
                'breakdown' => [],
                'source_meta' => ['created_via' => 'mcp_connector'],
            ],
        ]);

        $body = $this->getJson("/api/v1/admin/quotations/{$quotation->id}", $this->adminHeaders())
            ->assertOk()
            ->json('data');

        $this->assertSame('test_landing', $body['scope_display'][0]['package_key']);
        $this->assertSame(['extra_page' => 2], $body['scope_display'][0]['scope']);
        // form_payload stays available for the builder's own hydration.
        $this->assertArrayHasKey('form_payload', $body);
    }
}
