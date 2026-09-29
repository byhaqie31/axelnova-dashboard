<?php

namespace Tests\Feature\Revenue;

use App\Models\Client;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GET /v1/admin/revenue/monthly/{YYYY-MM} — the drill-down behind one row of the
 * monthly overview. It must use the SAME rules as the overview (orders by
 * created_at excluding cancelled; cash by paid_at over succeeded ledger rows,
 * refunds netting out) so the month page always adds up to its overview row.
 */
class RevenueMonthDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Mid-month, so "this month" / "last month" never straddle a boundary.
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));
    }

    private function adminHeaders(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function pay(Order $order, float $amount, string $paidAt, array $extra = []): Payment
    {
        return Payment::factory()->create(array_merge([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'amount_myr' => $amount,
            'paid_at' => Carbon::parse($paidAt),
        ], $extra));
    }

    public function test_month_detail_adds_up_to_its_overview_row(): void
    {
        $acme = Client::factory()->create(['name' => 'Acme']);
        $june = Order::factory()->create(['client_id' => $acme->id, 'final_amount_myr' => 10000, 'created_at' => '2026-06-10']);
        $july = Order::factory()->create(['final_amount_myr' => 6000, 'created_at' => '2026-07-03']);
        Order::factory()->create(['final_amount_myr' => 9999, 'status' => 'cancelled', 'created_at' => '2026-07-05']);

        $this->pay($june, 5000, '2026-06-10');
        $this->pay($june, 5000, '2026-07-08');   // June's balance lands in July
        $this->pay($july, 3000, '2026-07-04');

        $headers = $this->adminHeaders();
        $detail = $this->getJson('/api/v1/admin/revenue/monthly/2026-07', $headers)->assertOk();
        $overviewRow = collect($this->getJson('/api/v1/admin/revenue/monthly', $headers)->json('series'))
            ->firstWhere('month', '2026-07');

        $this->assertEquals($overviewRow['booked'], $detail->json('summary.booked'));
        $this->assertEquals($overviewRow['collected'], $detail->json('summary.collected'));
        $this->assertEquals($overviewRow['orders'], $detail->json('summary.orders'));
        $this->assertEquals($overviewRow['payments'], $detail->json('summary.payments'));

        // One sale closed (the cancelled one never counts), 6000 booked, 8000 banked.
        $this->assertSame(1, $detail->json('summary.orders'));
        $this->assertEquals(6000, $detail->json('summary.booked'));
        $this->assertEquals(8000, $detail->json('summary.collected'));
        // Outstanding = what's still owed on THIS month's orders.
        $this->assertEquals(3000, $detail->json('summary.outstanding'));
        $this->assertSame([$july->id], array_column($detail->json('orders'), 'id'));
    }

    public function test_payments_list_tags_orders_won_in_an_earlier_month(): void
    {
        $june = Order::factory()->create(['final_amount_myr' => 10000, 'created_at' => '2026-06-10']);
        $this->pay($june, 5000, '2026-07-08', ['method' => 'fpx', 'fee_myr' => 12.5]);

        $payments = $this->getJson('/api/v1/admin/revenue/monthly/2026-07', $this->adminHeaders())
            ->assertOk()
            ->json('payments');

        $this->assertCount(1, $payments);
        $this->assertSame('2026-06', $payments[0]['order_month']);
        $this->assertSame($june->order_number, $payments[0]['order_number']);
        $this->assertSame('fpx', $payments[0]['method']);
        $this->assertEquals(12.5, $payments[0]['fee']);
    }

    public function test_refunds_net_out_and_are_listed_as_negative_rows(): void
    {
        $order = Order::factory()->create(['final_amount_myr' => 4000, 'created_at' => '2026-07-02']);
        $paid = $this->pay($order, 4000, '2026-07-02');
        $this->pay($order, -1000, '2026-07-20', ['type' => 'refund', 'parent_payment_id' => $paid->id]);
        // Failed rows never count as cash.
        $this->pay($order, 999, '2026-07-21', ['status' => 'failed']);

        $res = $this->getJson('/api/v1/admin/revenue/monthly/2026-07', $this->adminHeaders())->assertOk();

        $this->assertEquals(3000, $res->json('summary.collected'));
        $this->assertEquals(1000, $res->json('summary.refunded'));
        $this->assertEqualsCanonicalizing([4000, -1000], array_column($res->json('payments'), 'amount'));
    }

    public function test_by_client_rolls_up_booked_and_collected(): void
    {
        $acme = Client::factory()->create(['name' => 'Acme']);
        $beta = Client::factory()->create(['name' => 'Beta']);
        $a = Order::factory()->create(['client_id' => $acme->id, 'final_amount_myr' => 5000, 'created_at' => '2026-07-01']);
        $old = Order::factory()->create(['client_id' => $beta->id, 'final_amount_myr' => 8000, 'created_at' => '2026-05-01']);
        $this->pay($a, 2500, '2026-07-01');
        $this->pay($old, 8000, '2026-07-09');

        $clients = collect($this->getJson('/api/v1/admin/revenue/monthly/2026-07', $this->adminHeaders())->json('clients'))
            ->keyBy('name');

        $this->assertEquals(5000, $clients['Acme']['booked']);
        $this->assertEquals(2500, $clients['Acme']['collected']);
        // Beta booked nothing this month but paid off an old order — still listed.
        $this->assertEquals(0, $clients['Beta']['booked']);
        $this->assertEquals(8000, $clients['Beta']['collected']);
        // Largest collected first.
        $this->assertSame('Beta', $this->getJson('/api/v1/admin/revenue/monthly/2026-07', $this->adminHeaders())->json('clients.0.name'));
    }

    public function test_order_rows_label_what_was_sold(): void
    {
        $quotation = Quotation::factory()->create(['document' => ['project' => 'Roofly booking site']]);
        Order::factory()->create(['quotation_id' => $quotation->id, 'created_at' => '2026-07-03']);

        $this->getJson('/api/v1/admin/revenue/monthly/2026-07', $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('orders.0.label', 'Roofly booking site');
    }

    public function test_an_empty_month_returns_zeroes_and_neighbour_links(): void
    {
        $this->getJson('/api/v1/admin/revenue/monthly/2026-03', $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('month', '2026-03')
            ->assertJsonPath('label', 'Mar 2026')
            ->assertJsonPath('prev', '2026-02')
            ->assertJsonPath('next', '2026-04')
            ->assertJsonPath('summary.orders', 0)
            ->assertJsonPath('orders', [])
            ->assertJsonPath('payments', []);

        // The current month has no "next" — the future isn't browsable.
        $this->getJson('/api/v1/admin/revenue/monthly/2026-07', $this->adminHeaders())
            ->assertJsonPath('next', null);
    }

    public function test_malformed_or_future_months_are_404(): void
    {
        $headers = $this->adminHeaders();

        $this->getJson('/api/v1/admin/revenue/monthly/2026-13', $headers)->assertNotFound();
        $this->getJson('/api/v1/admin/revenue/monthly/july', $headers)->assertNotFound();
        $this->getJson('/api/v1/admin/revenue/monthly/2026-08', $headers)->assertNotFound();
    }

    public function test_workspace_users_cannot_read_a_month(): void
    {
        $token = User::factory()->marketer()->create()->createToken('team-spa', ['workspace'])->plainTextToken;

        $this->getJson('/api/v1/admin/revenue/monthly/2026-07', ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();
    }
}
