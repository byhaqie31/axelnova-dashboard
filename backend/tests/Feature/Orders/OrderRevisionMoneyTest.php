<?php

namespace Tests\Feature\Orders;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderCarePlan;
use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * §4 money rules on supersede, end to end through the API and the ledger
 * (acceptance tests 3 and 4): unpaid invoices void, paid ones stay, the new
 * schedule is netted against paid-to-date with ONE adjustment invoice, an
 * overpayment is flagged, and Care Plan months are neither billed twice nor
 * skipped. The headline case is AXNQ-2026-0017 (M Automobile).
 */
class OrderRevisionMoneyTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 10:00:00');
        PricingConfig::factory()->create([
            'config' => ['currency' => 'MYR', 'valid_for_days' => 30, 'rush_multiplier' => 1.20,
                'base_packages' => [], 'modifiers' => [], 'addons' => []],
        ]);
        $this->founder = User::factory()->founder()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->founder->createToken('admin-spa', ['cockpit'])->plainTextToken];
    }

    private function document(float $total, array $plan): array
    {
        return array_merge([
            'layout' => 'detailed',
            'payload' => ['project' => 'Sistem Bengkel', 'sections' => [[
                'title' => 'Scope', 'rows' => [['title' => 'All', 'price' => $total]], 'total' => $total,
            ]]],
        ], $plan);
    }

    private function body(float $total, array $plan): array
    {
        return ['name' => 'M Automobile', 'email' => 'm-auto@example.com', 'document' => $this->document($total, $plan)];
    }

    private function acceptedOrder(float $total, array $plan): Order
    {
        $id = $this->postJson('/api/v1/admin/quotations', $this->body($total, $plan), $this->headers())->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/quotations/{$id}/accept", [], $this->headers())->assertOk();

        return Order::where('quotation_id', $id)->firstOrFail();
    }

    private function invoice(Order $order, array $body): Invoice
    {
        $id = $this->postJson("/api/v1/admin/orders/{$order->id}/documents", array_merge(['type' => 'invoice'], $body), $this->headers())
            ->assertCreated()->json('document.id');

        return Invoice::findOrFail($id);
    }

    private function pay(Order $order, float $amount, ?Invoice $invoice = null): void
    {
        $this->postJson("/api/v1/admin/orders/{$order->id}/payments", array_filter([
            'invoice_id' => $invoice?->id, 'amount_myr' => $amount, 'method' => 'bank_transfer',
            'reference' => 'TRX-'.uniqid(), 'paid_at' => now()->toDateString(),
        ]), $this->headers())->assertCreated();
    }

    /** Revise the order's quotation to $total / $plan and accept it (the supersede). */
    private function reviseAndAccept(Order $order, float $total, array $plan, string $reason = 'Client switched to the 40/40/20 plan'): void
    {
        $id = $order->quotation_id;
        $this->postJson("/api/v1/admin/quotations/{$id}/revisions", ['change_reason' => $reason], $this->headers())->assertCreated();
        $this->putJson("/api/v1/admin/quotations/{$id}", $this->body($total, $plan), $this->headers())->assertOk();
        $this->postJson("/api/v1/admin/quotations/{$id}/accept", [], $this->headers())->assertOk()->assertJsonPath('order_id', $order->id);
    }

    private const R1_INSTALMENT = [
        'deposit_amount_myr' => 2700, 'payment_plan' => 'instalment', 'instalment_months' => 12,
        'instalment_amount_myr' => 970, 'billing_day' => 20, 'first_instalment_date' => '2026-11-20',
        'includes_care_plan' => true,
    ];

    private const R2_PHASED = [
        'payment_plan' => 'lump_sum',
        'phases' => [['label' => 'On acceptance', 'pct' => 40], ['label' => 'At demo', 'pct' => 40], ['label' => 'At go-live', 'pct' => 20]],
        'care_plan_monthly_myr' => 220,
    ];

    public function test_m_automobile_instalment_to_40_40_20(): void
    {
        // R1 accepted 10 Oct: deposit RM 2,700 invoiced + paid; instalment 1 invoiced, unpaid.
        $order = $this->acceptedOrder(14340, self::R1_INSTALMENT);
        $deposit = $this->invoice($order, ['invoiceType' => 'deposit', 'amount' => 2700]);
        $this->pay($order, 2700, $deposit);
        $instalment = $this->invoice($order, ['invoiceType' => 'instalment', 'instalmentNo' => 1, 'amount' => 970]);
        $r1 = $order->current_revision_id;

        // 11 Oct: the client switches to the 40/40/20 lump sum on RM 10,800.
        Carbon::setTestNow('2026-10-11 09:00:00');
        $this->reviseAndAccept($order, 10800, self::R2_PHASED);

        $order->refresh();
        $this->assertSame(1, Order::count(), 'the order survives the revision');
        $this->assertNotSame($r1, $order->current_revision_id);
        $this->assertSame(10800.0, (float) $order->final_amount_myr);
        $this->assertSame(2700.0, (float) $order->amount_paid_myr, 'paid-to-date stays the ledger sum');
        $this->assertSame(4320.0, $order->deposit_due_myr, 'phase 1 is the up-front figure');

        // §4.2 — paid kept, unpaid voided (number stays in the register).
        $this->assertSame('paid', $deposit->fresh()->status);
        $this->assertSame($r1, $deposit->fresh()->revision_id);
        $this->assertSame('void', $instalment->fresh()->status);
        $this->assertSame('Superseded by R2', $instalment->fresh()->void_reason);

        // §4.3 — one adjustment invoice for RM 1,620, tagged R2.
        $adjustment = Invoice::where('order_id', $order->id)->where('type', 'adjustment')->sole();
        $this->assertSame(1620.0, (float) $adjustment->amount_total);
        $this->assertSame('issued', $adjustment->status);
        $this->assertSame($order->current_revision_id, $adjustment->revision_id);
        $this->assertSame('Deposit top-up, R1 → R2 due', collect($adjustment->payload['summary']['rows'])->firstWhere('total', true)['label']);
        $this->assertSame(6480.0, (float) collect($adjustment->payload['summary']['rows'])->firstWhere('role', 'remaining')['price'], '4,320 + 2,160 still to come');

        $plan = $this->getJson("/api/v1/admin/orders/{$order->id}", $this->headers())->assertOk()->json('data.payment_plan');
        $this->assertTrue($plan['phased']);
        $this->assertSame(
            [['adjustment', 2700, 1620], ['due', 0, 4320], ['due', 0, 2160]],
            array_map(fn ($p) => [$p['netting']['state'], (int) $p['netting']['covered_myr'], (int) $p['netting']['due_myr']], $plan['phases']),
        );
        $this->assertSame($adjustment->invoice_number, $plan['phases'][0]['netting']['invoice']['number']);
        $this->assertSame(2, $plan['next_phase_no']);
        $this->assertSame([$instalment->invoice_number], $plan['netting']['voided_invoices']);

        // The netted schedule guards billing: phase 1 / a deposit can't be billed again;
        // phase 2 can, once.
        $this->postJson("/api/v1/admin/orders/{$order->id}/documents", ['type' => 'invoice', 'invoiceType' => 'phase', 'instalmentNo' => 1, 'amount' => 4320], $this->headers())
            ->assertStatus(422)->assertJsonValidationErrors(['instalmentNo']);
        $this->postJson("/api/v1/admin/orders/{$order->id}/documents", ['type' => 'invoice', 'invoiceType' => 'deposit', 'amount' => 4320], $this->headers())
            ->assertStatus(422)->assertJsonValidationErrors(['invoiceType']);
        $phase2 = $this->invoice($order, ['invoiceType' => 'phase', 'instalmentNo' => 2, 'amount' => 4320]);
        $this->assertSame('Phase 2 of 3 · At demo due', collect($phase2->payload['summary']['rows'])->firstWhere('total', true)['label']);
        $this->postJson("/api/v1/admin/orders/{$order->id}/documents", ['type' => 'invoice', 'invoiceType' => 'phase', 'instalmentNo' => 2, 'amount' => 4320], $this->headers())
            ->assertStatus(422);

        // §3.5 — Care Plan: R1's included care closes on 11 Oct (no month was paid),
        // R2's separate RM 220/month opens 1 Nov 2026 — where R1's was due to start.
        [$old, $new] = OrderCarePlan::where('order_id', $order->id)->orderBy('id')->get()->all();
        $this->assertSame([OrderCarePlan::INCLUDED, '2026-10-11', '2026-10-31'], [$old->kind, $old->ended_at->toDateString(), $old->ends_on->toDateString()]);
        $this->assertSame([OrderCarePlan::SEPARATE, '2026-11-01', 220.0], [$new->kind, $new->starts_on->toDateString(), (float) $new->monthly_myr]);
        $this->assertEquals(['from' => 'included', 'to' => 'separate'], array_intersect_key($order->revision_flags['care_plan_changed'], ['from' => 1, 'to' => 1]));
    }

    public function test_care_plan_months_are_never_billed_twice_or_skipped(): void
    {
        // R1 instalments 1 + 2 (Nov, Dec 2026) paid — their care is covered.
        $order = $this->acceptedOrder(14340, self::R1_INSTALMENT);
        $this->pay($order, 2700, $this->invoice($order, ['invoiceType' => 'deposit', 'amount' => 2700]));
        foreach ([1, 2] as $n) {
            $this->pay($order, 970, $this->invoice($order, ['invoiceType' => 'instalment', 'instalmentNo' => $n, 'amount' => 970]));
        }

        Carbon::setTestNow('2027-01-05 09:00:00');
        $this->reviseAndAccept($order, 10800, self::R2_PHASED);

        [$old, $new] = OrderCarePlan::where('order_id', $order->id)->orderBy('id')->get()->all();
        $this->assertSame('2026-12-31', $old->ends_on->toDateString());
        $this->assertSame('2027-01-01', $new->starts_on->toDateString());
        $this->assertTrue($old->ends_on->addDay()->equalTo($new->starts_on), 'contiguous: nothing skipped');

        $care = fn (string $month) => $this->postJson("/api/v1/admin/orders/{$order->id}/documents", [
            'type' => 'invoice', 'invoiceType' => 'care_plan', 'carePeriod' => $month, 'amount' => 220,
        ], $this->headers());

        $care('2026-12')->assertStatus(422)->assertJsonValidationErrors(['carePeriod']); // paid inside instalment 2
        $care('2027-01')->assertCreated();
        $care('2027-01')->assertStatus(422)->assertJsonValidationErrors(['carePeriod']); // twice
        $care('2027-02')->assertCreated();

        $this->assertSame(
            ['2027-01-01', '2027-02-01'],
            Invoice::where('type', 'care_plan')->orderBy('care_period')->get()->map(fn ($i) => $i->care_period->toDateString())->all(),
        );
        $this->assertSame('Care Plan · January 2027 due', collect(Invoice::where('type', 'care_plan')->orderBy('care_period')->first()->payload['summary']['rows'])->firstWhere('total', true)['label']);
    }

    public function test_a_part_paid_invoice_is_voided_and_its_payment_carried(): void
    {
        $order = $this->acceptedOrder(10000, ['deposit_pct' => 50]);
        $deposit = $this->invoice($order, ['invoiceType' => 'deposit', 'amount' => 5000]);
        $this->pay($order, 3000, $deposit);

        $this->reviseAndAccept($order, 8000, ['payment_plan' => 'lump_sum', 'phases' => [['pct' => 50], ['pct' => 50]]], 'Scope trimmed');

        $deposit->refresh();
        $this->assertSame('void', $deposit->status);
        $this->assertSame('Superseded by R2 — RM 3,000.00 paid on it is carried into the new schedule', $deposit->void_reason);
        $this->assertSame(3000.0, (float) $order->fresh()->amount_paid_myr, 'the payment stays on the ledger');

        // P = 3,000 against [4,000, 4,000] → adjustment 1,000 on phase 1.
        $this->assertSame(1000.0, (float) Invoice::where('order_id', $order->id)->where('type', 'adjustment')->sole()->amount_total);
    }

    public function test_paid_beyond_the_revised_total_flags_overpaid_and_issues_nothing(): void
    {
        $order = $this->acceptedOrder(10000, ['deposit_pct' => 50]);
        $this->pay($order, 9000);

        $this->reviseAndAccept($order, 8000, ['deposit_pct' => 50], 'Descoped');

        $order->refresh();
        $this->assertSame(1000.0, (float) $order->revision_flags['overpaid_myr']);
        $this->assertSame(0, Invoice::where('order_id', $order->id)->where('type', 'adjustment')->count());
        $this->getJson("/api/v1/admin/orders/{$order->id}", $this->headers())
            ->assertJsonPath('data.payment_plan.netting.overpaid_myr', 1000);
    }

    public function test_instalment_numbers_restart_on_the_new_revision(): void
    {
        // R1 instalment 1 paid; R2 is another instalment plan — its instalment 1 is new.
        $order = $this->acceptedOrder(14340, self::R1_INSTALMENT);
        $this->pay($order, 2700, $this->invoice($order, ['invoiceType' => 'deposit', 'amount' => 2700]));
        $this->pay($order, 970, $this->invoice($order, ['invoiceType' => 'instalment', 'instalmentNo' => 1, 'amount' => 970]));

        $this->reviseAndAccept($order, 13000, [
            'deposit_amount_myr' => 1000, 'payment_plan' => 'instalment', 'instalment_months' => 10,
            'instalment_amount_myr' => 1200, 'billing_day' => 20, 'first_instalment_date' => '2026-12-20',
        ], 'Longer runway');

        // P = 3,670 → deposit 1,000 settled, instalments 1–2 settled (2,400), instalment 3 adjusts 1,200 − 270 = 930.
        $plan = $this->getJson("/api/v1/admin/orders/{$order->id}", $this->headers())->json('data.payment_plan');
        $this->assertSame('settled', $plan['deposit_netting']['state']);
        $this->assertSame(['settled', 'settled', 'adjustment', 'due'], array_map(fn ($r) => $r['netting']['state'], array_slice($plan['schedule'], 0, 4)));
        $this->assertNull($plan['schedule'][0]['invoice'], "R1's paid instalment 1 is not R2's instalment 1");
        $this->assertSame(4, $plan['next_instalment_no']);
        $this->assertSame(930.0, (float) Invoice::where('order_id', $order->id)->where('type', 'adjustment')->sole()->amount_total);
        $this->assertSame('Instalment 3 of 10 top-up, R1 → R2 due', collect(Invoice::where('type', 'adjustment')->sole()->payload['summary']['rows'])->firstWhere('total', true)['label']);
    }
}
