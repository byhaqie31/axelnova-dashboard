<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Client::upsertContact — per-FIELD last-write-wins for trusted writers (the
 * admin builder and the MCP connector). A supplied non-empty field updates the
 * stored record; an absent, null, or empty-string field preserves it. The old
 * firstOrCreate behaviour silently dropped every supplied value once the email
 * matched an existing row (the "UPM" vs "UPM Consultancy & Services" bug).
 */
class ClientUpsertContactTest extends TestCase
{
    use RefreshDatabase;

    private function existingClient(): Client
    {
        return Client::create([
            'name' => 'Nordahlia Umar',
            'email' => 'nordahlia@upm.edu.my',
            'phone' => '+0124970501',
            'company' => 'UPM',
        ]);
    }

    public function test_supplied_different_field_updates_the_existing_client(): void
    {
        $client = $this->existingClient();

        $result = Client::upsertContact([
            'name' => 'Nordahlia Umar',
            'email' => 'nordahlia@upm.edu.my',
            'company' => 'UPM Consultancy & Services',
        ]);

        $this->assertTrue($result->is($client));
        $this->assertSame('UPM Consultancy & Services', $result->fresh()->company);
    }

    public function test_absent_fields_are_preserved(): void
    {
        $this->existingClient();

        // No phone or company keys at all — both must survive untouched.
        $result = Client::upsertContact([
            'name' => 'Nordahlia Umar',
            'email' => 'nordahlia@upm.edu.my',
        ]);

        $this->assertSame('+0124970501', $result->fresh()->phone);
        $this->assertSame('UPM', $result->fresh()->company);
    }

    public function test_empty_string_and_null_fields_are_preserved_not_wiped(): void
    {
        $this->existingClient();

        $result = Client::upsertContact([
            'name' => '',
            'email' => 'nordahlia@upm.edu.my',
            'phone' => null,
            'company' => '  ',
        ]);

        $fresh = $result->fresh();
        $this->assertSame('Nordahlia Umar', $fresh->name);
        $this->assertSame('+0124970501', $fresh->phone);
        $this->assertSame('UPM', $fresh->company);
    }

    public function test_unknown_email_creates_a_new_client(): void
    {
        $result = Client::upsertContact([
            'name' => 'Fresh Contact',
            'email' => 'fresh@example.com',
            'phone' => '+60123456789',
        ]);

        $this->assertTrue($result->wasRecentlyCreated);
        $this->assertDatabaseHas('clients', [
            'email' => 'fresh@example.com',
            'name' => 'Fresh Contact',
            'phone' => '+60123456789',
        ]);
    }

    /**
     * End-to-end on the reported path: update_draft_quotation supplies a fuller
     * company — the client record AND the quotation's denormalised snapshot
     * (via ClientObserver) must both pick it up, while the untouched phone
     * survives the sparse payload.
     */
    public function test_connector_update_applies_supplied_company_without_wiping_phone(): void
    {
        PricingConfig::factory()->create();
        $founder = User::factory()->founder()->create();
        Cache::flush();

        $header = ['Authorization' => 'Bearer '.$founder
            ->createToken('mcp-connector', ['connector:read', 'connector:draft'])->plainTextToken];

        $ref = $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => [
                'name' => 'Nordahlia Umar',
                'email' => 'nordahlia@upm.edu.my',
                'phone' => '+0124970501',
                'company' => 'UPM',
            ],
            'line_items' => [['label' => 'Discovery workshop', 'amount_myr' => 1500]],
        ], $header)->assertCreated()->json('data.reference_code');

        // Sparse update: company supplied (and different), phone absent.
        $this->putJson("/api/v1/connector/quotations/{$ref}", [
            'client' => [
                'name' => 'Nordahlia Umar',
                'email' => 'nordahlia@upm.edu.my',
                'company' => 'UPM Consultancy & Services',
            ],
            'line_items' => [['label' => 'Discovery workshop', 'amount_myr' => 1500]],
            'reseed_document' => true,
        ], $header)->assertOk();

        $this->assertDatabaseHas('clients', [
            'email' => 'nordahlia@upm.edu.my',
            'company' => 'UPM Consultancy & Services',
            'phone' => '+0124970501',
        ]);

        $quotation = Quotation::where('reference_code', $ref)->firstOrFail();
        $this->assertSame('UPM Consultancy & Services', $quotation->company);
        $this->assertSame('+0124970501', $quotation->phone);
    }
}
