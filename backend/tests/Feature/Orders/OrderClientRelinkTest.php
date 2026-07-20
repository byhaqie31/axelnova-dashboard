<?php

namespace Tests\Feature\Orders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Correcting a mis-matched order's client on the detail page. Orders carry no
 * contact columns — the card reads them through the linked Client — so "edit"
 * and "re-link" both resolve to pointing order.client_id at the right client.
 * Re-linking an order cascades to its source quotation (the pair is one deal),
 * and never rewrites already-issued invoices (frozen snapshots).
 */
class OrderClientRelinkTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_relinks_an_order_to_an_existing_client(): void
    {
        $wrong = Client::factory()->create(['name' => 'Wrong Co']);
        $right = Client::factory()->create(['name' => 'Right Co', 'email' => 'right@example.com']);
        $order = Order::factory()->create(['client_id' => $wrong->id]);

        $this->postJson("/api/v1/admin/orders/{$order->id}/client", [
            'client_id' => $right->id,
        ], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('order.client_id', $right->id)
            ->assertJsonPath('order.email', 'right@example.com')
            ->assertJsonPath('linked_existing', false);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'client_id' => $right->id]);
    }

    public function test_relink_cascades_to_the_source_quotation_snapshot(): void
    {
        $wrong = Client::factory()->create();
        $right = Client::factory()->create(['name' => 'Right Co', 'email' => 'right@example.com']);

        $quotation = Quotation::factory()->create([
            'client_id' => $wrong->id,
            'name' => $wrong->name,
            'email' => $wrong->email,
        ]);
        $order = Order::factory()->create(['client_id' => $wrong->id, 'quotation_id' => $quotation->id]);

        $this->postJson("/api/v1/admin/orders/{$order->id}/client", [
            'client_id' => $right->id,
        ], $this->adminHeaders())->assertOk();

        // The source quotation follows the order onto the correct client, and its
        // denormalised snapshot columns are refreshed to match.
        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'client_id' => $right->id,
            'name' => 'Right Co',
            'email' => 'right@example.com',
        ]);
    }

    public function test_relink_create_new_with_a_new_email_creates_and_links_a_client(): void
    {
        $order = Order::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->postJson("/api/v1/admin/orders/{$order->id}/client", [
            'client' => [
                'name' => 'Brand New',
                'email' => 'brand.new@example.com',
                'phone' => '0100000000',
                'company' => 'New Corp',
            ],
        ], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('linked_existing', false)
            ->assertJsonPath('order.email', 'brand.new@example.com');

        $this->assertDatabaseHas('clients', ['email' => 'brand.new@example.com', 'name' => 'Brand New']);
        $new = Client::where('email', 'brand.new@example.com')->firstOrFail();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'client_id' => $new->id]);
    }

    public function test_relink_create_new_with_an_existing_email_links_to_the_existing_client(): void
    {
        $existing = Client::factory()->create(['name' => 'Existing Co', 'email' => 'dup@example.com']);
        $order = Order::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->postJson("/api/v1/admin/orders/{$order->id}/client", [
            'client' => [
                'name' => 'A Different Name',
                'email' => 'dup@example.com',
            ],
        ], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('linked_existing', true)
            ->assertJsonPath('order.client_id', $existing->id);

        // No duplicate created, and the existing client keeps its own details
        // (create-new matched by email → link, don't overwrite).
        $this->assertSame(1, Client::where('email', 'dup@example.com')->count());
        $this->assertDatabaseHas('clients', ['id' => $existing->id, 'name' => 'Existing Co']);
    }

    public function test_relink_does_not_rewrite_an_already_issued_invoice(): void
    {
        $wrong = Client::factory()->create(['name' => 'Wrong Co']);
        $right = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $wrong->id]);
        // A frozen invoice snapshot billed to the (then-current) wrong client.
        $invoice = Invoice::factory()->create([
            'order_id' => $order->id,
            'payload' => ['bill_to' => ['name' => 'Wrong Co']],
        ]);

        $this->postJson("/api/v1/admin/orders/{$order->id}/client", [
            'client_id' => $right->id,
        ], $this->adminHeaders())->assertOk();

        // The issued invoice records who was billed at the time — untouched.
        $this->assertSame('Wrong Co', $invoice->fresh()->payload['bill_to']['name']);
    }

    public function test_relink_requires_a_client_id_or_a_client_object(): void
    {
        $order = Order::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->postJson("/api/v1/admin/orders/{$order->id}/client", [], $this->adminHeaders())
            ->assertStatus(422);
    }
}
