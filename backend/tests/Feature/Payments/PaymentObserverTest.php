<?php

namespace Tests\Feature\Payments;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Referral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PaymentObserver is the sole writer of the derived paid caches. These tests
 * pin the ledger contract: signed SUM over succeeded rows, refunds as negative
 * child rows, caches clamped at zero, void invoices never auto-flipped.
 */
class PaymentObserverTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $attributes = []): Order
    {
        return Order::factory()->create($attributes);
    }

    private function pay(Order $order, float $amount, array $attributes = []): Payment
    {
        return Payment::factory()->create([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'amount_myr' => $amount,
            ...$attributes,
        ]);
    }

    public function test_a_succeeded_payment_updates_the_order_paid_cache(): void
    {
        $order = $this->makeOrder();

        $this->pay($order, 1000);

        $this->assertSame('1000.00', $order->refresh()->amount_paid_myr);
    }

    public function test_pending_and_failed_payments_do_not_count(): void
    {
        $order = $this->makeOrder();

        $this->pay($order, 500, ['status' => 'pending']);
        $this->pay($order, 500, ['status' => 'failed']);

        $this->assertSame('0.00', $order->refresh()->amount_paid_myr);
    }

    public function test_a_refund_subtracts_from_the_order_cache(): void
    {
        $order = $this->makeOrder();
        $payment = $this->pay($order, 1000);

        Payment::factory()->refundOf($payment, 400)->create();

        $this->assertSame('600.00', $order->refresh()->amount_paid_myr);
    }

    public function test_the_order_cache_never_goes_negative(): void
    {
        $order = $this->makeOrder();
        $payment = $this->pay($order, 1000);

        Payment::factory()->refundOf($payment, 1200)->create();

        $this->assertSame('0.00', $order->refresh()->amount_paid_myr);
    }

    public function test_an_invoice_flips_to_paid_when_fully_covered(): void
    {
        $order = $this->makeOrder();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1250]);

        $this->pay($order, 1250, ['invoice_id' => $invoice->id]);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('1250.00', $invoice->amount_paid);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_a_partial_payment_keeps_the_invoice_issued(): void
    {
        $order = $this->makeOrder();
        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'amount_total' => 1250]);

        $this->pay($order, 500, ['invoice_id' => $invoice->id]);

        $invoice->refresh();
        $this->assertSame('issued', $invoice->status);
        $this->assertSame('500.00', $invoice->amount_paid);
        $this->assertNull($invoice->paid_at);
    }

    public function test_a_void_invoice_is_never_auto_flipped(): void
    {
        $order = $this->makeOrder();
        $invoice = Invoice::factory()->create([
            'order_id' => $order->id,
            'amount_total' => 1250,
            'status' => 'void',
        ]);

        $this->pay($order, 1250, ['invoice_id' => $invoice->id]);

        $this->assertSame('void', $invoice->refresh()->status);
    }

    public function test_a_referral_converts_when_money_lands_and_reverts_on_full_refund(): void
    {
        $order = $this->makeOrder();
        $referral = Referral::create([
            'referrer_name' => 'Ref Erra',
            'referrer_email' => 'ref@example.com',
            'business_name' => 'Acme Sdn Bhd',
            'relationship_tier' => 'warm',
            'commission_tier_pct' => 10,
            'status' => 'draft',
            'quotation_id' => $order->quotation_id,
        ]);

        $payment = $this->pay($order, 1000);
        $this->assertSame('converted', $referral->refresh()->status);

        Payment::factory()->refundOf($payment)->create();
        $this->assertSame('draft', $referral->refresh()->status);
    }
}
