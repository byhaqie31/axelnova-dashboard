<?php

namespace Tests\Feature\Connector;

use App\Models\Order;
use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * get_order (connector v5.1) — the READ-ONLY money view Claude uses to check an
 * order tallies after a quotation revision is accepted: agreed total = paid +
 * open invoiced + still to bill, the netted plan, every invoice with its status
 * and void reason. The connector never issues or edits an invoice.
 */
class ConnectorOrderTest extends TestCase
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

    // Forget the cached guard user when switching tokens (connector ↔ cockpit).
    private function connector(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->founder->createToken('mcp-connector', ['connector:read', 'connector:draft'])->plainTextToken];
    }

    private function admin(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->founder->createToken('admin-spa', ['cockpit'])->plainTextToken];
    }

    private function detailed(float $total, array $plan): array
    {
        return array_merge(['sections' => [['title' => 'Skop', 'rows' => [['title' => 'Semua', 'amount_myr' => $total]]]]], $plan);
    }

    /**
     * M Automobile: R1 instalment (RM 2,700 deposit paid, instalment 1 invoiced but
     * unpaid), revised by the founder, edited through the connector to 40/40/20
     * on RM 10,800, and accepted in the admin. Returns [reference, order].
     *
     * @return array{0: string, 1: Order}
     */
    private function revisedMAutomobile(): array
    {
        $client = ['name' => 'M Automobile', 'email' => 'm-auto@example.com'];
        $ref = $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => $client,
            'detailed' => $this->detailed(14340, [
                'deposit_amount_myr' => 2700, 'payment_plan' => 'instalment', 'instalment_months' => 12,
                'instalment_amount_myr' => 970, 'first_instalment_date' => '2026-11-20', 'includes_care_plan' => true,
            ]),
        ], $this->connector())->assertCreated()->json('data.reference_code');
        $id = Quotation::where('reference_code', $ref)->value('id');

        // Not accepted yet → no order to read.
        $this->getJson("/api/v1/connector/orders/{$ref}", $this->connector())
            ->assertNotFound()
            ->assertJsonPath('message', "Quotation {$ref} has no order yet — it becomes one when the founder accepts it in the admin (it is 'draft').");

        $this->postJson("/api/v1/admin/quotations/{$id}/accept", [], $this->admin())->assertOk();
        $order = Order::where('quotation_id', $id)->firstOrFail();

        $deposit = $this->postJson("/api/v1/admin/orders/{$order->id}/documents", ['type' => 'invoice', 'invoiceType' => 'deposit', 'amount' => 2700], $this->admin())
            ->assertCreated()->json('document.id');
        $this->postJson("/api/v1/admin/orders/{$order->id}/payments", [
            'invoice_id' => $deposit, 'amount_myr' => 2700, 'method' => 'bank_transfer', 'reference' => 'TRX-1', 'paid_at' => '2026-10-10',
        ], $this->admin())->assertCreated();
        $this->postJson("/api/v1/admin/orders/{$order->id}/documents", ['type' => 'invoice', 'invoiceType' => 'instalment', 'instalmentNo' => 1, 'amount' => 970], $this->admin())
            ->assertCreated();

        Carbon::setTestNow('2026-10-11 09:00:00');
        // The founder opens the revision; the connector only edits the draft.
        $this->postJson("/api/v1/admin/quotations/{$id}/revisions", ['change_reason' => 'Client switched to the 40/40/20 plan'], $this->admin())->assertCreated();
        $this->putJson("/api/v1/connector/quotations/{$ref}", [
            'client' => $client,
            'detailed' => $this->detailed(10800, [
                'payment_plan' => 'lump_sum',
                'phases' => [['label' => 'On acceptance', 'pct' => 40], ['label' => 'At demo', 'pct' => 40], ['label' => 'At go-live', 'pct' => 20]],
                'care_plan_monthly_myr' => 220,
            ]),
            'reseed_document' => true,
        ], $this->connector())->assertOk();
        $this->postJson("/api/v1/admin/quotations/{$id}/accept", [], $this->admin())->assertOk();

        return [$ref, $order->fresh()];
    }

    public function test_the_order_tallies_after_the_revision_is_accepted(): void
    {
        [$ref, $order] = $this->revisedMAutomobile();

        $res = $this->getJson("/api/v1/connector/orders/{$ref}", $this->connector())
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.quotation.display_reference', "{$ref} · R2")
            ->assertJsonPath('data.quotation.revision_number', 2)
            // 10,800 = 2,700 paid + 1,620 adjustment open + 6,480 still to bill.
            ->assertJsonPath('data.tally.agreed_total_myr', 10800)
            ->assertJsonPath('data.tally.paid_to_date_myr', 2700)
            ->assertJsonPath('data.tally.open_invoiced_myr', 1620)
            ->assertJsonPath('data.tally.still_to_bill_myr', 6480)
            ->assertJsonPath('data.tally.over_billed_myr', 0)
            ->assertJsonPath('data.tally.tallies', true)
            ->assertJsonPath('data.payment_plan.phased', true)
            ->assertJsonPath('data.payment_plan.phases.0.netting.state', 'adjustment')
            ->assertJsonPath('data.payment_plan.phases.1.netting.state', 'due')
            ->assertJsonPath('data.flags.care_plan_changed.to', 'separate')
            ->assertJsonPath('data.revisions.0.status', 'superseded')
            ->assertJsonPath('data.revisions.1.status', 'accepted');

        $invoices = collect($res->json('data.invoices'))->keyBy('type');
        $this->assertSame(['paid', 1, 'Deposit'], [$invoices['deposit']['status'], $invoices['deposit']['revision_number'], $invoices['deposit']['label']]);
        $this->assertSame(['void', 'Superseded by R2', 'Instalment 1'], [$invoices['instalment']['status'], $invoices['instalment']['void_reason'], $invoices['instalment']['label']]);
        // assertEquals: JSON decodes 1620.0 as the int 1620.
        $this->assertEquals(['issued', 2, 1620, 'Deposit top-up, R1 → R2'], [
            $invoices['adjustment']['status'], $invoices['adjustment']['revision_number'],
            $invoices['adjustment']['amount_myr'], $invoices['adjustment']['label'],
        ]);
        $this->assertEquals(2700, $res->json('data.payments.0.amount_myr'));
        $this->assertArrayNotHasKey('pdf_path', $res->json('data.invoices.0'), 'no public PDF tokens over the connector');

        // Same view by the AXNO order number.
        $this->getJson("/api/v1/connector/orders/{$order->order_number}", $this->connector())
            ->assertOk()->assertJsonPath('data.tally.still_to_bill_myr', 6480);
    }

    public function test_it_flags_an_order_whose_live_invoices_ask_for_more_than_is_left(): void
    {
        [$ref, $order] = $this->revisedMAutomobile();

        // A hand-issued partial bill on top of the adjustment overshoots the total.
        $this->postJson("/api/v1/admin/orders/{$order->id}/documents", ['type' => 'invoice', 'invoiceType' => 'partial', 'amount' => 8000], $this->admin())
            ->assertCreated();

        $this->getJson("/api/v1/connector/orders/{$ref}", $this->connector())
            ->assertOk()
            ->assertJsonPath('data.tally.open_invoiced_myr', 9620)
            ->assertJsonPath('data.tally.still_to_bill_myr', 0)
            ->assertJsonPath('data.tally.over_billed_myr', 1520)
            ->assertJsonPath('data.tally.tallies', false);
    }

    public function test_unknown_references_and_non_connector_tokens_are_refused(): void
    {
        $this->getJson('/api/v1/connector/orders/AXNO-2001-9999', $this->connector())->assertNotFound();
        $this->getJson('/api/v1/connector/orders/AXNQ-2001-9999', $this->connector())
            ->assertNotFound()
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'list_quotations'));

        $this->getJson('/api/v1/connector/orders/AXNO-2001-9999', $this->admin())->assertForbidden();
    }
}
