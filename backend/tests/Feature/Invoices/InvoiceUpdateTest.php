<?php

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Quoting\DocumentIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * In-place invoice editing: the payload is re-frozen from the stored issue
 * inputs (same AXNI number, same public token), amount-bearing fields lock
 * once money is recorded, void invoices are read-only, and legacy invoices
 * (issued before `inputs` existed) still take notes/due-date edits without
 * their totals drifting.
 */
class InvoiceUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $founder = User::factory()->founder()->create();
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function issueInvoice(Order $order, array $input = []): Invoice
    {
        return DocumentIssuer::issueInvoice($order, array_merge([
            'invoiceType' => 'final',
            'amount' => 1300,
            'notes' => 'Balance Payment',
        ], $input));
    }

    public function test_editing_regenerates_the_payload_in_place(): void
    {
        $order = Order::factory()->create(['final_amount_myr' => 2600]);
        $invoice = $this->issueInvoice($order);
        $number = $invoice->invoice_number;
        $token = $invoice->public_token;

        $this->putJson("/api/v1/admin/invoices/{$invoice->id}", [
            'amount' => 1500,
            'notes' => 'Updated note',
        ], $this->adminHeaders())->assertOk();

        $invoice->refresh();
        $this->assertSame($number, $invoice->invoice_number);
        $this->assertSame($token, $invoice->public_token);
        $this->assertSame('1500.00', $invoice->amount_total);
        // Notes land in the canonical NoteLine[] shape the PDF template maps over.
        $this->assertEquals([['label' => '', 'text' => 'Updated note']], $invoice->payload['notes']);
        // The merged inputs round-trip for the next edit.
        $this->assertSame(1500, $invoice->inputs['amount']);
        $this->assertSame('Updated note', $invoice->inputs['notes']);
    }

    public function test_a_present_null_clears_a_stored_input(): void
    {
        $order = Order::factory()->create(['final_amount_myr' => 2600]);
        $invoice = $this->issueInvoice($order, [
            'discountType' => 'amount',
            'discountValue' => 100,
        ]);
        $this->assertSame('1200.00', $invoice->amount_total);

        $this->putJson("/api/v1/admin/invoices/{$invoice->id}", [
            'discountType' => null,
            'discountValue' => null,
        ], $this->adminHeaders())->assertOk();

        $this->assertSame('1300.00', $invoice->refresh()->amount_total);
        $this->assertArrayNotHasKey('discountValue', $invoice->inputs);
    }

    public function test_amounts_lock_once_a_payment_is_recorded(): void
    {
        $order = Order::factory()->create(['final_amount_myr' => 2600]);
        $invoice = $this->issueInvoice($order);
        Payment::factory()->create([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'invoice_id' => $invoice->id,
            'amount_myr' => 500,
        ]);

        // Amount-bearing fields are rejected…
        $this->putJson("/api/v1/admin/invoices/{$invoice->id}", [
            'amount' => 9999,
        ], $this->adminHeaders())->assertUnprocessable();

        // …but notes and due date stay editable, and the total doesn't move.
        $this->putJson("/api/v1/admin/invoices/{$invoice->id}", [
            'notes' => 'Corrected note',
            'dueAt' => '2026-08-01',
        ], $this->adminHeaders())->assertOk();

        $invoice->refresh();
        $this->assertSame('1300.00', $invoice->amount_total);
        $this->assertEquals([['label' => '', 'text' => 'Corrected note']], $invoice->payload['notes']);
        $this->assertSame('2026-08-01', $invoice->due_at->toDateString());
    }

    public function test_void_invoices_are_read_only(): void
    {
        $order = Order::factory()->create(['final_amount_myr' => 2600]);
        $invoice = $this->issueInvoice($order, ['status' => 'void']);

        $this->putJson("/api/v1/admin/invoices/{$invoice->id}", [
            'notes' => 'nope',
        ], $this->adminHeaders())->assertConflict();
    }

    public function test_paid_invoices_are_fully_read_only(): void
    {
        $order = Order::factory()->create(['final_amount_myr' => 2600]);
        $invoice = $this->issueInvoice($order);
        // Full settlement — the observer flips the invoice to paid.
        Payment::factory()->create([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'invoice_id' => $invoice->id,
            'amount_myr' => 1300,
        ]);
        $this->assertSame('paid', $invoice->refresh()->status);

        // Even a notes-only edit is refused — paid invoices are frozen records.
        $this->putJson("/api/v1/admin/invoices/{$invoice->id}", [
            'notes' => 'nope',
        ], $this->adminHeaders())->assertConflict();
    }

    public function test_legacy_invoices_without_inputs_take_note_edits_without_total_drift(): void
    {
        $order = Order::factory()->create(['final_amount_myr' => 2600]);
        $invoice = Invoice::factory()->create([
            'order_id' => $order->id,
            'type' => 'deposit',
            'inputs' => null,
            'amount_total' => 1300,
            'payload' => ['issued' => '01 July 2026', 'notes' => 'Old string note'],
        ]);

        $this->putJson("/api/v1/admin/invoices/{$invoice->id}", [
            'notes' => 'Fresh note',
        ], $this->adminHeaders())->assertOk();

        $invoice->refresh();
        $this->assertSame('1300.00', $invoice->amount_total);
        $this->assertSame('01 July 2026', $invoice->payload['issued']);
        $this->assertEquals([['label' => '', 'text' => 'Fresh note']], $invoice->payload['notes']);
        // First save writes proper inputs for the next round-trip. (Loose
        // comparison — MySQL JSON normalizes 1300.0 to the integer 1300.)
        $this->assertEquals(1300, $invoice->inputs['amount']);
    }

    public function test_update_requires_admin_auth(): void
    {
        $order = Order::factory()->create();
        $invoice = $this->issueInvoice($order);

        $this->putJson("/api/v1/admin/invoices/{$invoice->id}", ['notes' => 'x'])
            ->assertUnauthorized();
    }
}
