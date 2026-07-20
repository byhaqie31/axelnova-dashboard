<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editing a client's contact details (the "Edit details" mode) writes to the
 * shared Client. Orders read contact through the client live, but quotations keep
 * a denormalised snapshot — so a ClientObserver re-syncs every quotation snapshot
 * when a client's contact fields change, keeping quotation cards/PDFs correct.
 */
class ClientSnapshotSyncTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_editing_a_client_resyncs_its_quotation_snapshots(): void
    {
        $client = Client::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
        $quotation = Quotation::factory()->create([
            'client_id' => $client->id,
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $this->putJson("/api/v1/admin/clients/{$client->id}", [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'phone' => '0111111111',
            'company' => 'New Corp',
        ], $this->adminHeaders())->assertOk();

        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
            'company' => 'New Corp',
        ]);
    }

    public function test_client_edit_only_touches_its_own_quotations(): void
    {
        $a = Client::factory()->create();
        $b = Client::factory()->create();
        $qa = Quotation::factory()->create(['client_id' => $a->id, 'name' => $a->name, 'email' => $a->email]);
        $qb = Quotation::factory()->create(['client_id' => $b->id, 'name' => $b->name, 'email' => $b->email]);

        $this->putJson("/api/v1/admin/clients/{$a->id}", [
            'name' => 'A Changed',
            'email' => 'a.changed@example.com',
        ], $this->adminHeaders())->assertOk();

        $this->assertDatabaseHas('quotations', ['id' => $qa->id, 'name' => 'A Changed']);
        // Client B's quotation is untouched.
        $this->assertDatabaseHas('quotations', ['id' => $qb->id, 'name' => $b->name]);
    }
}
