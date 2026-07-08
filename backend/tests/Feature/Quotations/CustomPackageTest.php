<?php

namespace Tests\Feature\Quotations;

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Non-catalog quotes (bespoke line items / detailed proposals — package_key null)
 * surface a derived "Custom" package descriptor: a label from the project title
 * (falling back to 'Custom') and a via_connector provenance flag. Catalog quotes
 * return null. This is what lets the list show "Custom · Axelnova MCP" instead of
 * an empty package cell.
 */
class CustomPackageTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_catalog_quote_has_no_custom_package(): void
    {
        $q = Quotation::factory()->create(['package_key' => 'web_business']);

        $this->assertNull($q->customPackage());
    }

    public function test_bespoke_quote_labels_from_project_and_flags_connector(): void
    {
        $q = Quotation::factory()->create([
            'package_key' => null,
            'document' => ['project' => 'Acme e-commerce build', 'items' => [['title' => 'x', 'rate' => 100]]],
            'form_payload' => ['packages' => [], 'source_meta' => ['created_via' => 'mcp_connector']],
        ]);

        $this->assertSame(
            ['label' => 'Acme e-commerce build', 'via_connector' => true],
            $q->customPackage(),
        );
    }

    public function test_detailed_quote_reads_project_and_falls_back_to_custom(): void
    {
        // Detailed, no project → label falls back to 'Custom'; admin-made → not via connector.
        $q = Quotation::factory()->create([
            'package_key' => null,
            'document' => ['layout' => 'detailed', 'payload' => ['sections' => []]],
            'form_payload' => ['packages' => [], 'source_meta' => ['created_via' => 'admin']],
        ]);

        $this->assertSame(
            ['label' => 'Custom', 'via_connector' => false],
            $q->customPackage(),
        );
    }

    public function test_admin_list_exposes_custom_package_on_slim_rows(): void
    {
        Quotation::factory()->create([
            'reference_code' => 'AXNQ-2099-0777',
            'package_key' => null,
            'status' => 'draft',
            'document' => ['project' => 'Custom migration', 'items' => [['title' => 'x', 'rate' => 100]]],
            'form_payload' => ['packages' => [], 'source_meta' => ['created_via' => 'mcp_connector']],
        ]);

        $row = collect($this->getJson('/api/v1/admin/quotations?status=draft', $this->adminHeaders())
            ->assertOk()
            ->json('data'))
            ->firstWhere('reference_code', 'AXNQ-2099-0777');

        $this->assertSame('Custom migration', $row['custom_package']['label']);
        $this->assertTrue($row['custom_package']['via_connector']);
        // Slim list row still omits the heavy document payload.
        $this->assertArrayNotHasKey('document', $row);
    }
}
