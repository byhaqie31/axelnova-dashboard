<?php

namespace Tests\Feature\Payments;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PaymentService::allocate() — moving a payment's invoice link after the
 * fact. Both sides' caches recompute, refund children and the receipt's
 * display link follow the move.
 */
class PaymentAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function pay(Order $order, float $amount, array $attributes = []): Payment
    {
        return Payment::factory()->create([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'amount_myr' => $amount,
            ...$attributes,
        ]);
    }

    public function test_linking_a_covering_payment_marks_the_invoice_paid(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 1000);

        PaymentService::allocate($payment, $invoice);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('1000.00', $invoice->amount_paid);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame($invoice->id, $payment->refresh()->invoice_id);
    }

    public function test_linking_a_partial_payment_keeps_the_invoice_issued(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 400);

        PaymentService::allocate($payment, $invoice);

        $invoice->refresh();
        $this->assertSame('issued', $invoice->status);
        $this->assertSame('400.00', $invoice->amount_paid);
        $this->assertNull($invoice->paid_at);
    }

    public function test_relinking_recomputes_both_invoices(): void
    {
        $order = Order::factory()->create();
        $invoiceA = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 500]);
        $invoiceB = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 500]);
        $payment = $this->pay($order, 500, ['invoice_id' => $invoiceA->id]);
        $this->assertSame('paid', $invoiceA->refresh()->status);

        PaymentService::allocate($payment, $invoiceB);

        $invoiceA->refresh();
        $this->assertSame('issued', $invoiceA->status);
        $this->assertSame('0.00', $invoiceA->amount_paid);
        $this->assertNull($invoiceA->paid_at);

        $invoiceB->refresh();
        $this->assertSame('paid', $invoiceB->status);
        $this->assertSame('500.00', $invoiceB->amount_paid);
    }

    public function test_unlinking_reverts_the_old_invoice(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 500]);
        $payment = $this->pay($order, 500, ['invoice_id' => $invoice->id]);
        $this->assertSame('paid', $invoice->refresh()->status);

        PaymentService::allocate($payment, null);

        $invoice->refresh();
        $this->assertSame('issued', $invoice->status);
        $this->assertSame('0.00', $invoice->amount_paid);
        $this->assertNull($payment->refresh()->invoice_id);
    }

    public function test_refund_children_follow_the_allocation(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 1000);
        $refund = Payment::factory()->refundOf($payment, 400)->create();

        PaymentService::allocate($payment, $invoice);

        // The negative refund row moved too, so the invoice sum is net.
        $this->assertSame($invoice->id, $refund->refresh()->invoice_id);
        $invoice->refresh();
        $this->assertSame('600.00', $invoice->amount_paid);
        $this->assertSame('issued', $invoice->status);
    }

    public function test_an_issued_receipt_follows_the_allocation(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 1000);
        $receipt = Receipt::create([
            'order_id' => $order->id,
            'invoice_id' => null,
            'payment_id' => $payment->id,
            'receipt_number' => 'AXNR-2001-0001',
            'public_token' => str_repeat('r', 48),
            'payload' => [],
            'amount' => 1000,
            'payment_ref' => 'TEST-REF',
            'payment_method' => 'bank_transfer',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        PaymentService::allocate($payment, $invoice);

        $this->assertSame($invoice->id, $receipt->refresh()->invoice_id);
    }

    private function adminHeaders(): array
    {
        $founder = User::factory()->founder()->create();
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function patchAllocation(Payment $payment, ?int $invoiceId)
    {
        return $this->patchJson(
            "/api/v1/admin/payments/{$payment->id}/allocation",
            ['invoice_id' => $invoiceId],
            $this->adminHeaders(),
        );
    }

    public function test_the_endpoint_links_and_returns_the_updated_payment(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 1000);

        $this->patchAllocation($payment, $invoice->id)
            ->assertOk()
            ->assertJsonPath('payment.invoice_id', $invoice->id)
            ->assertJsonPath('payment.invoice_number', $invoice->invoice_number);

        $this->assertSame('paid', $invoice->refresh()->status);
    }

    public function test_a_noop_reallocation_returns_ok_without_changes(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 400, ['invoice_id' => $invoice->id]);

        $this->patchAllocation($payment, $invoice->id)->assertOk();

        $this->assertSame('400.00', $invoice->refresh()->amount_paid);
    }

    public function test_an_invoice_from_another_order_is_rejected(): void
    {
        $order = Order::factory()->create();
        $otherInvoice = Invoice::factory()->create([
            'order_id' => Order::factory()->create()->id,
            'amount_total' => 1000,
        ]);
        $payment = $this->pay($order, 1000);

        $this->patchAllocation($payment, $otherInvoice->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('invoice_id');
    }

    public function test_a_void_invoice_is_rejected(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create([
            'order_id' => $order->id,
            'amount_total' => 1000,
            'status' => 'void',
        ]);
        $payment = $this->pay($order, 1000);

        $this->patchAllocation($payment, $invoice->id)->assertStatus(422);
    }

    public function test_a_refund_row_cannot_be_allocated(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 1000);
        $refund = Payment::factory()->refundOf($payment, 400)->create();

        $this->patchAllocation($refund, $invoice->id)->assertStatus(422);
    }

    public function test_a_non_succeeded_payment_cannot_be_allocated(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1000]);
        $payment = $this->pay($order, 1000, ['status' => 'pending']);

        $this->patchAllocation($payment, $invoice->id)->assertStatus(422);
    }

    public function test_the_endpoint_unlinks_with_a_null_invoice_id(): void
    {
        $order = Order::factory()->create();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 500]);
        $payment = $this->pay($order, 500, ['invoice_id' => $invoice->id]);

        $this->patchAllocation($payment, null)
            ->assertOk()
            ->assertJsonPath('payment.invoice_id', null);

        $invoice->refresh();
        $this->assertSame('issued', $invoice->status);
        $this->assertSame('0.00', $invoice->amount_paid);
    }

    public function test_a_body_missing_invoice_id_is_rejected(): void
    {
        $order = Order::factory()->create();
        $payment = $this->pay($order, 500);

        $this->patchJson(
            "/api/v1/admin/payments/{$payment->id}/allocation",
            [],
            $this->adminHeaders(),
        )->assertStatus(422)->assertJsonValidationErrors('invoice_id');
    }
}
