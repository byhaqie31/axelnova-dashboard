<?php

namespace Tests\Feature\Quotations;

use App\Models\Client;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Correcting a mis-matched quotation's client on the detail page. A quotation
 * shows its OWN snapshot columns (name/email/phone/company), so re-linking must
 * both re-point client_id AND refresh the snapshot from the new client.
 * Available regardless of lifecycle status (the bad records may be sent/accepted).
 */
class QuotationClientRelinkTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_relinks_a_quotation_to_an_existing_client_and_syncs_the_snapshot(): void
    {
        $wrong = Client::factory()->create();
        $right = Client::factory()->create(['name' => 'Right Co', 'email' => 'right@example.com', 'company' => 'Right Sdn Bhd']);
        $quotation = Quotation::factory()->create([
            'status' => 'sent',
            'client_id' => $wrong->id,
            'name' => $wrong->name,
            'email' => $wrong->email,
        ]);

        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/client", [
            'client_id' => $right->id,
        ], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.client_id', $right->id)
            ->assertJsonPath('data.email', 'right@example.com')
            ->assertJsonPath('data.company', 'Right Sdn Bhd');

        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'client_id' => $right->id,
            'name' => 'Right Co',
            'email' => 'right@example.com',
            'company' => 'Right Sdn Bhd',
        ]);
    }

    public function test_relink_create_new_with_an_existing_email_links_to_the_existing_client(): void
    {
        $existing = Client::factory()->create(['name' => 'Existing Co', 'email' => 'dup@example.com']);
        $quotation = Quotation::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/client", [
            'client' => ['name' => 'Different', 'email' => 'dup@example.com'],
        ], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('linked_existing', true)
            ->assertJsonPath('data.client_id', $existing->id);

        $this->assertSame(1, Client::where('email', 'dup@example.com')->count());
    }

    public function test_relink_requires_a_client_id_or_a_client_object(): void
    {
        $quotation = Quotation::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->postJson("/api/v1/admin/quotations/{$quotation->id}/client", [], $this->adminHeaders())
            ->assertStatus(422);
    }
}
