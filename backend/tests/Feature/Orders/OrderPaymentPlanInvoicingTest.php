<?php

namespace Tests\Feature\Orders;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Quoting\PaymentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invoicing an order follows the quotation it came from:
 *   • a percentage deposit rounds exactly like the quotation (nearest ringgit),
 *     so the deposit invoice never differs from the quoted figure by cents;
 *   • an instalment / partner plan is snapshotted onto the order on accept, and
 *     each monthly invoice is an `instalment` invoice tied to its number — the
 *     amount, label and due date come from the agreed schedule, and the same
 *     instalment can't be invoiced twice.
 */
class OrderPaymentPlanInvoicingTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        PricingConfig::factory()->create([
            'config' => ['currency' => 'MYR', 'valid_for_days' => 30, 'rush_multiplier' => 1.20,
                'base_packages' => [], 'modifiers' => [], 'addons' => []],
        ]);
        $this->founder = User::factory()->founder()->create();
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->founder->createToken('admin-spa', ['cockpit'])->plainTextToken];
    }

    /** Create a detailed RM 14,340 quotation with the given plan keys and accept it. */
    private function acceptedOrder(array $documentExtras): Order
    {
        $id = $this->postJson('/api/v1/admin/quotations', [
            'name' => 'M Automobile', 'email' => 'm-auto@example.com',
            'document' => array_merge([
                'layout' => 'detailed',
                'deposit_pct' => 50,
                'payload' => ['project' => 'Sistem Bengkel', 'sections' => [[
                    'title' => 'Scope', 'rows' => [['title' => 'All', 'price' => 14340]], 'total' => 14340,
                ]]],
            ], $documentExtras),
        ], $this->headers())->assertCreated()->json('data.id');

        $this->postJson("/api/v1/admin/quotations/{$id}/accept", [], $this->headers())->assertOk();

        return Order::where('quotation_id', $id)->firstOrFail();
    }

    private function instalmentOrder(array $overrides = []): Order
    {
        return $this->acceptedOrder(array_merge([
            'deposit_amount_myr' => 2700, 'payment_plan' => 'instalment', 'instalment_months' => 12,
            'instalment_amount_myr' => 970, 'billing_day' => 20, 'first_instalment_date' => '2026-11-20',
            'includes_care_plan' => true,
        ], $overrides));
    }

    private function partnerOrder(): Order
    {
        return $this->acceptedOrder([
            'deposit_amount_myr' => 2340, 'payment_plan' => 'partner', 'instalment_months' => 24,
            'instalment_amount_myr' => 500, 'billing_day' => 1, 'first_instalment_date' => '2026-12-01',
        ]);
    }

    private function issue(Order $order, array $body)
    {
        return $this->postJson("/api/v1/admin/orders/{$order->id}/documents", array_merge(['type' => 'invoice'], $body), $this->headers());
    }

    // ── Gap 1: deposit rounding ──────────────────────────────────────────────

    public function test_pct_deposit_on_the_order_rounds_like_the_quotation(): void
    {
        $order = $this->acceptedOrder(['deposit_pct' => 19]);

        // 19% of RM 14,340 = RM 2,724.60 → the quotation prints RM 2,725.
        $this->assertSame(2725.0, PaymentPlan::fromDocument(['deposit_pct' => 19], 14340)->depositAmount());
        $this->assertSame(2725.0, $order->deposit_due_myr);
        $this->getJson("/api/v1/admin/orders/{$order->id}", $this->headers())
            ->assertJsonPath('data.deposit_due_myr', 2725);
    }

    // ── Gap 2: the plan travels onto the order ───────────────────────────────

    public function test_accept_snapshots_the_instalment_plan_onto_the_order(): void
    {
        $order = $this->instalmentOrder();

        // assertEquals: the JSON column round-trip reorders keys and drops the ".0".
        $this->assertEquals([
            'payment_plan' => 'instalment', 'instalment_months' => 12, 'instalment_amount_myr' => 970.0,
            'billing_day' => 20, 'first_instalment_date' => '2026-11-20', 'includes_care_plan' => true,
        ], $order->payment_plan);

        $res = $this->getJson("/api/v1/admin/orders/{$order->id}", $this->headers())->assertOk();
        $res->assertJsonPath('data.payment_plan.plan', 'instalment')
            ->assertJsonPath('data.payment_plan.months', 12)
            ->assertJsonPath('data.payment_plan.monthly_myr', 970)
            ->assertJsonPath('data.payment_plan.deposit_myr', 2700)
            ->assertJsonPath('data.payment_plan.plan_total_myr', 14340)
            ->assertJsonPath('data.payment_plan.next_instalment_no', 1)
            ->assertJsonPath('data.payment_plan.schedule.0.n', 1)
            ->assertJsonPath('data.payment_plan.schedule.0.date', '2026-11-20')
            ->assertJsonPath('data.payment_plan.schedule.0.label', 'Instalment 1 of 12')
            ->assertJsonPath('data.payment_plan.schedule.0.invoice', null)
            ->assertJsonPath('data.payment_plan.schedule.11.date', '2027-10-20');
    }

    public function test_accept_resolves_a_blank_first_date_into_the_snapshot(): void
    {
        $order = $this->instalmentOrder(['first_instalment_date' => null]);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-20$/', $order->payment_plan['first_instalment_date']);
    }

    public function test_lump_sum_orders_carry_no_plan(): void
    {
        $order = $this->acceptedOrder(['deposit_amount_myr' => 2700]);

        $this->assertNull($order->payment_plan);
        $this->getJson("/api/v1/admin/orders/{$order->id}", $this->headers())->assertJsonPath('data.payment_plan', null);
    }

    public function test_orders_accepted_before_the_snapshot_read_the_plan_from_their_quotation(): void
    {
        $order = $this->instalmentOrder();
        $order->forceFill(['payment_plan' => null])->saveQuietly();

        $this->getJson("/api/v1/admin/orders/{$order->id}", $this->headers())
            ->assertJsonPath('data.payment_plan.plan', 'instalment')
            ->assertJsonPath('data.payment_plan.schedule.0.date', '2026-11-20');
    }

    // ── Instalment invoices ──────────────────────────────────────────────────

    public function test_an_instalment_invoice_takes_its_amount_label_and_due_date_from_the_schedule(): void
    {
        $order = $this->instalmentOrder();

        $res = $this->issue($order, ['invoiceType' => 'instalment', 'instalmentNo' => 3, 'amount' => 970])->assertCreated();

        $invoice = Invoice::findOrFail($res->json('document.id'));
        $this->assertSame('instalment', $invoice->type);
        $this->assertSame(3, $invoice->instalment_no);
        $this->assertEquals(970, $invoice->amount_total);
        $this->assertSame('2027-01-20', $invoice->due_at->toDateString());
        $this->assertSame('Instalment 3 of 12 invoice', $invoice->payload['status']);
        $labels = array_column($invoice->payload['summary']['rows'], 'label');
        $this->assertContains('Instalment 3 of 12 due', $labels);

        // The order's schedule shows instalment 3 as invoiced; the next one is 1.
        $this->getJson("/api/v1/admin/orders/{$order->id}", $this->headers())
            ->assertJsonPath('data.payment_plan.schedule.2.invoice.number', $invoice->invoice_number)
            ->assertJsonPath('data.payment_plan.schedule.2.invoice.status', 'issued')
            ->assertJsonPath('data.payment_plan.next_instalment_no', 1);
    }

    public function test_the_same_instalment_cannot_be_invoiced_twice_unless_voided(): void
    {
        $order = $this->instalmentOrder();
        $first = $this->issue($order, ['invoiceType' => 'instalment', 'instalmentNo' => 1, 'amount' => 970])->assertCreated();

        $this->issue($order, ['invoiceType' => 'instalment', 'instalmentNo' => 1, 'amount' => 970])
            ->assertStatus(422)->assertJsonValidationErrors(['instalmentNo']);

        Invoice::whereKey($first->json('document.id'))->update(['status' => 'void']);
        $this->issue($order, ['invoiceType' => 'instalment', 'instalmentNo' => 1, 'amount' => 970])->assertCreated();
    }

    public function test_instalment_invoices_are_validated_against_the_plan(): void
    {
        $order = $this->instalmentOrder();
        $this->issue($order, ['invoiceType' => 'instalment', 'amount' => 970])
            ->assertStatus(422)->assertJsonValidationErrors(['instalmentNo']);
        $this->issue($order, ['invoiceType' => 'instalment', 'instalmentNo' => 13, 'amount' => 970])
            ->assertStatus(422)->assertJsonValidationErrors(['instalmentNo']);

        $lump = $this->acceptedOrder(['deposit_pct' => 50]);
        $this->issue($lump, ['invoiceType' => 'instalment', 'instalmentNo' => 1, 'amount' => 970])
            ->assertStatus(422)->assertJsonValidationErrors(['invoiceType']);
    }

    public function test_partner_invoices_are_labelled_setup_fee_and_monthly_fee(): void
    {
        $order = $this->partnerOrder();

        $setup = $this->issue($order, ['invoiceType' => 'deposit', 'amount' => 2340])->assertCreated();
        $this->assertSame('Setup fee invoice', Invoice::findOrFail($setup->json('document.id'))->payload['status']);

        $month = $this->issue($order, ['invoiceType' => 'instalment', 'instalmentNo' => 2, 'amount' => 500])->assertCreated();
        $invoice = Invoice::findOrFail($month->json('document.id'));
        $this->assertSame('Monthly fee 2 of 24 invoice', $invoice->payload['status']);
        $this->assertSame('2027-01-01', $invoice->due_at->toDateString());
    }

    public function test_editing_an_instalment_invoice_cannot_move_it_onto_an_invoiced_number(): void
    {
        $order = $this->instalmentOrder();
        $this->issue($order, ['invoiceType' => 'instalment', 'instalmentNo' => 1, 'amount' => 970])->assertCreated();
        $second = $this->issue($order, ['invoiceType' => 'instalment', 'instalmentNo' => 2, 'amount' => 970])->assertCreated();
        $id = $second->json('document.id');

        $this->putJson("/api/v1/admin/invoices/{$id}", ['invoiceType' => 'instalment', 'instalmentNo' => 1], $this->headers())
            ->assertStatus(422)->assertJsonValidationErrors(['instalmentNo']);

        // Keeping its own number (any other edit) is fine.
        $this->putJson("/api/v1/admin/invoices/{$id}", ['invoiceType' => 'instalment', 'instalmentNo' => 2, 'notes' => 'Auto-debit'], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.instalment_no', 2);
    }

    public function test_preview_labels_an_instalment_invoice(): void
    {
        $order = $this->instalmentOrder();

        $this->postJson("/api/v1/admin/orders/{$order->id}/documents/preview", [
            'invoiceType' => 'instalment', 'instalmentNo' => 5, 'amount' => 970,
        ], $this->headers())->assertOk()->assertJsonPath('status', 'Instalment 5 of 12 invoice');
    }
}
