<?php

namespace Tests\Feature\Clients;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Feedback;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DELETE /v1/admin/clients/{client}. A client with any quotation, order or
 * payment can only be deleted by naming a replacement — everything tied to it
 * (quotations incl. snapshot, orders, payments, inquiries, feedback; soft-deleted
 * rows too) moves across in one transaction, then the row is removed for good
 * so its email is free to reuse. A client with no money/quote ties deletes directly.
 */
class ClientDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function feedbackFor(Client $client): Feedback
    {
        return Feedback::create([
            'reference_code' => 'AXNF-2026-'.fake()->unique()->numerify('####'),
            'public_token' => Str::random(48),
            'client_id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
        ]);
    }

    /** A client with the full spread of ties, incl. a soft-deleted quotation. */
    private function tiedClient(): array
    {
        $old = Client::factory()->create(['email' => 'old@example.com']);
        $quotation = Quotation::factory()->create(['client_id' => $old->id, 'name' => $old->name, 'email' => $old->email]);
        $trashedQuote = Quotation::factory()->create(['client_id' => $old->id]);
        $trashedQuote->delete();
        $order = Order::factory()->create(['client_id' => $old->id, 'quotation_id' => $quotation->id]);
        $payment = Payment::factory()->create(['client_id' => $old->id, 'order_id' => $order->id]);
        $inquiry = Inquiry::create([
            'client_id' => $old->id, 'name' => $old->name, 'email' => $old->email,
            'message' => 'Hello there, interested.', 'status' => 'quoted',
        ]);
        $feedback = $this->feedbackFor($old);

        return compact('old', 'quotation', 'trashedQuote', 'order', 'payment', 'inquiry', 'feedback');
    }

    public function test_a_client_without_ties_is_deleted_permanently(): void
    {
        $client = Client::factory()->create(['email' => 'solo@example.com']);
        $inquiry = Inquiry::create([
            'client_id' => $client->id, 'name' => 'Solo', 'email' => 'solo@example.com',
            'message' => 'Just a question here.', 'status' => 'new',
        ]);

        $this->deleteJson("/api/v1/admin/clients/{$client->id}", [], $this->adminHeaders())
            ->assertOk();

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
        // The inquiry survives, just unlinked (FK null-on-delete).
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'client_id' => null]);
    }

    public function test_a_client_with_ties_is_blocked_without_a_replacement(): void
    {
        ['old' => $old] = $this->tiedClient();

        $res = $this->deleteJson("/api/v1/admin/clients/{$old->id}", [], $this->adminHeaders())
            ->assertStatus(409);

        $res->assertJsonPath('counts.quotations', 2);
        $res->assertJsonPath('counts.orders', 1);
        $res->assertJsonPath('counts.payments', 1);
        $this->assertDatabaseHas('clients', ['id' => $old->id]);
    }

    public function test_everything_moves_to_an_existing_replacement_before_delete(): void
    {
        $t = $this->tiedClient();
        $new = Client::factory()->create(['name' => 'Right Co', 'email' => 'right@example.com', 'phone' => '0123']);

        $this->deleteJson("/api/v1/admin/clients/{$t['old']->id}", ['client_id' => $new->id], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('replacement_id', $new->id);

        $this->assertDatabaseMissing('clients', ['id' => $t['old']->id]);
        $this->assertDatabaseHas('quotations', [
            'id' => $t['quotation']->id, 'client_id' => $new->id,
            'name' => 'Right Co', 'email' => 'right@example.com', 'phone' => '0123',
        ]);
        $this->assertDatabaseHas('quotations', ['id' => $t['trashedQuote']->id, 'client_id' => $new->id]);
        $this->assertDatabaseHas('orders', ['id' => $t['order']->id, 'client_id' => $new->id]);
        $this->assertDatabaseHas('payments', ['id' => $t['payment']->id, 'client_id' => $new->id]);
        $this->assertDatabaseHas('inquiries', ['id' => $t['inquiry']->id, 'client_id' => $new->id]);
        $this->assertDatabaseHas('feedback', ['id' => $t['feedback']->id, 'client_id' => $new->id]);

        $log = ActivityLog::where('action', 'client.deleted')->firstOrFail();
        $this->assertSame($t['old']->id, $log->subject_id);
        $this->assertSame($new->id, $log->changes['replacement_id']);
    }

    public function test_replacement_can_be_a_new_client_and_the_old_email_is_reusable(): void
    {
        $t = $this->tiedClient();

        $res = $this->deleteJson("/api/v1/admin/clients/{$t['old']->id}", [
            'client' => ['name' => 'Fresh Sdn Bhd', 'email' => 'fresh@example.com'],
        ], $this->adminHeaders())->assertOk();

        $fresh = Client::where('email', 'fresh@example.com')->firstOrFail();
        $res->assertJsonPath('replacement_id', $fresh->id);
        $this->assertDatabaseHas('orders', ['id' => $t['order']->id, 'client_id' => $fresh->id]);

        // Hard delete freed the unique email — it can be used again.
        $this->postJson('/api/v1/admin/clients', ['name' => 'Old Again', 'email' => 'old@example.com'], $this->adminHeaders())
            ->assertCreated();
    }

    public function test_the_client_cannot_replace_itself(): void
    {
        ['old' => $old] = $this->tiedClient();

        // By id, and via "create new" with its own email (which resolves to itself).
        $this->deleteJson("/api/v1/admin/clients/{$old->id}", ['client_id' => $old->id], $this->adminHeaders())
            ->assertUnprocessable();
        $this->deleteJson("/api/v1/admin/clients/{$old->id}", [
            'client' => ['name' => 'Same', 'email' => 'old@example.com'],
        ], $this->adminHeaders())->assertUnprocessable();

        $this->assertDatabaseHas('clients', ['id' => $old->id]);
    }

    public function test_detail_exposes_payment_count_for_the_delete_dialog(): void
    {
        ['old' => $old] = $this->tiedClient();

        $this->getJson("/api/v1/admin/clients/{$old->id}", $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.payments_count', 1);
    }

    public function test_workspace_users_cannot_delete_clients(): void
    {
        $client = Client::factory()->create();
        $token = User::factory()->marketer()->create()->createToken('team-spa', ['workspace'])->plainTextToken;

        $this->deleteJson("/api/v1/admin/clients/{$client->id}", [], ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();

        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }
}
