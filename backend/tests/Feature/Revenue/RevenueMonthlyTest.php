<?php

namespace Tests\Feature\Revenue;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /v1/admin/revenue/monthly — booked (orders won) vs collected (ledger cash)
 * per calendar month.
 *
 * The traps this guards: refunds are negative ledger rows and must net out of
 * their own month rather than be filtered away; quiet months must still occupy
 * a slot or the chart compresses them out; and month bucketing happens in SQL,
 * so it has to agree with the app timezone.
 */
class RevenueMonthlyTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $founder = User::factory()->founder()->create();
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function monthOf(array $series, string $key): array
    {
        $row = collect($series)->firstWhere('month', $key);
        $this->assertNotNull($row, "expected month {$key} in the series");

        return $row;
    }

    public function test_collected_buckets_by_paid_at_and_booked_by_order_month(): void
    {
        $order = Order::factory()->create([
            'final_amount_myr' => 8000,
            'created_at' => now()->subMonths(2),
        ]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'amount_myr' => 4000,
            'paid_at' => now()->subMonths(2),
        ]);
        // Balance lands two months later — the whole reason both series exist.
        Payment::factory()->create([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'amount_myr' => 4000,
            'paid_at' => now(),
        ]);

        $series = $this->getJson('/api/v1/admin/revenue/monthly', $this->adminHeaders())
            ->assertOk()
            ->json('series');

        $won = $this->monthOf($series, now()->subMonths(2)->format('Y-m'));
        $this->assertSame(8000.0, (float) $won['booked'], 'the full contract books in the month it was won');
        $this->assertSame(4000.0, (float) $won['collected'], 'only the deposit was collected then');

        $now = $this->monthOf($series, now()->format('Y-m'));
        $this->assertSame(0.0, (float) $now['booked'], 'no new order was won this month');
        $this->assertSame(4000.0, (float) $now['collected'], 'the balance collects now');
    }

    public function test_a_refund_nets_out_of_the_month_it_lands_in(): void
    {
        $order = Order::factory()->create(['created_at' => now()]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'amount_myr' => 3000,
            'paid_at' => now(),
        ]);
        Payment::factory()->refundOf($payment, 1200)->create(['paid_at' => now()]);

        $series = $this->getJson('/api/v1/admin/revenue/monthly', $this->adminHeaders())
            ->assertOk()
            ->json('series');

        $row = $this->monthOf($series, now()->format('Y-m'));
        $this->assertSame(1800.0, (float) $row['collected'], 'signed SUM nets the refund off');
        $this->assertSame(1200.0, (float) $row['refunded'], 'and it stays visible on its own');
    }

    public function test_quiet_months_are_zero_filled_and_the_window_is_dense(): void
    {
        $body = $this->getJson('/api/v1/admin/revenue/monthly?months=6', $this->adminHeaders())
            ->assertOk()
            ->json();

        $this->assertSame(6, $body['months']);
        $this->assertCount(6, $body['series']);
        $this->assertSame(now()->subMonths(5)->format('Y-m'), $body['series'][0]['month']);
        $this->assertSame(now()->format('Y-m'), $body['series'][5]['month']);
        $this->assertSame(0.0, (float) $body['series'][0]['collected']);
    }

    public function test_only_succeeded_payments_and_non_cancelled_orders_count(): void
    {
        $order = Order::factory()->create([
            'final_amount_myr' => 5000,
            'created_at' => now(),
        ]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'amount_myr' => 900,
            'status' => 'pending',
            'paid_at' => now(),
        ]);
        Order::factory()->create([
            'final_amount_myr' => 9999,
            'status' => 'cancelled',
            'created_at' => now(),
        ]);

        $row = $this->monthOf(
            $this->getJson('/api/v1/admin/revenue/monthly', $this->adminHeaders())->assertOk()->json('series'),
            now()->format('Y-m'),
        );

        $this->assertSame(0.0, (float) $row['collected'], 'a pending payment is not cash');
        $this->assertSame(5000.0, (float) $row['booked'], 'the cancelled order is not a sale');
    }

    public function test_totals_report_net_of_gateway_fees(): void
    {
        $order = Order::factory()->create(['created_at' => now()]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'amount_myr' => 2000,
            'fee_myr' => 58.40,
            'paid_at' => now(),
        ]);

        $totals = $this->getJson('/api/v1/admin/revenue/monthly', $this->adminHeaders())
            ->assertOk()
            ->json('totals');

        $this->assertSame(2000.0, (float) $totals['collected']);
        $this->assertSame(58.4, (float) $totals['fees']);
        $this->assertSame(1941.6, (float) $totals['net']);
    }

    public function test_an_unknown_range_falls_back_to_twelve_months(): void
    {
        $body = $this->getJson('/api/v1/admin/revenue/monthly?months=999', $this->adminHeaders())
            ->assertOk()
            ->json();

        $this->assertSame(12, $body['months']);
        $this->assertCount(12, $body['series']);
    }

    public function test_the_endpoint_is_closed_to_anonymous_callers(): void
    {
        $this->getJson('/api/v1/admin/revenue/monthly')->assertUnauthorized();
    }
}
