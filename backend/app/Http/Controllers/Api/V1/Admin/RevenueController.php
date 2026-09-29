<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Monthly money reporting for the founder cockpit.
 *
 * Deliberately its own controller rather than a method on AnalyticsController:
 * that one is exposed to the marketer surface via `role:founder,marketer`, and
 * revenue must not ride along on a route shared with a non-founder role.
 *
 * Reads only — no table of its own. Everything here is derived from the
 * `payments` ledger and `orders`; see docs/global/PAYMENTS-LEDGER.md.
 */
class RevenueController extends Controller
{
    /** Selectable window sizes, in months. */
    private const RANGES = [6, 12, 24];

    /**
     * Booked vs collected, per calendar month, oldest → newest.
     *
     * Two numbers that are easy to conflate and must not be:
     *
     * - **booked** — contracted value of orders won in the month
     *   (`orders.final_amount_myr`). What we sold.
     * - **collected** — cash that actually landed (`payments.paid_at`). What we
     *   banked.
     *
     * With 50% deposit terms these diverge by months: an order won in January
     * collects half in January and half on delivery. The gap between the two
     * series is the point of the page, so both are always returned.
     */
    public function monthly(Request $request): JsonResponse
    {
        $months = (int) $request->query('months', '12');
        if (! in_array($months, self::RANGES, true)) {
            $months = 12;
        }

        // Inclusive window of whole calendar months ending with the current one.
        // APP_TIMEZONE is Asia/Kuala_Lumpur and Laravel stores timestamps in app
        // time, so these bounds and the SQL DATE_FORMAT below agree on where a
        // month starts. Don't swap either side for UTC without changing both.
        $from = now()->startOfMonth()->subMonths($months - 1);
        $to = now()->endOfMonth();

        // Collected: signed SUM over succeeded rows. Refunds are negative rows
        // (see PaymentStatus) so they net out here on their own — filtering them
        // out would overstate every month they land in. `refunded` is reported
        // separately so a bad month stays visible instead of silently absorbed.
        // Hits index(['status', 'paid_at']).
        $collected = Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as m")
            ->selectRaw('SUM(amount_myr) as collected')
            ->selectRaw('SUM(fee_myr) as fees')
            ->selectRaw('SUM(CASE WHEN amount_myr < 0 THEN -amount_myr ELSE 0 END) as refunded')
            ->selectRaw('COUNT(*) as payments')
            ->groupBy('m')
            ->get()
            ->keyBy('m');

        // Booked: contracted value of the orders won that month. Cancelled work
        // never counted as a sale, matching OrdersController::stats.
        $booked = Order::query()
            ->whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as m")
            ->selectRaw('SUM(final_amount_myr) as booked')
            ->selectRaw('COUNT(*) as orders')
            ->groupBy('m')
            ->get()
            ->keyBy('m');

        // Dense, zero-filled series — a month with no activity must still occupy
        // a slot or the chart silently compresses quiet months out of existence.
        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $cursor = $from->copy()->addMonths($i);
            $key = $cursor->format('Y-m');
            $c = $collected->get($key);
            $b = $booked->get($key);

            $series[] = [
                'month' => $key,
                'label' => $cursor->format('M Y'),
                'collected' => round((float) ($c->collected ?? 0), 2),
                'fees' => round((float) ($c->fees ?? 0), 2),
                'refunded' => round((float) ($c->refunded ?? 0), 2),
                'payments' => (int) ($c->payments ?? 0),
                'booked' => round((float) ($b->booked ?? 0), 2),
                'orders' => (int) ($b->orders ?? 0),
            ];
        }

        $sum = fn (string $k) => round(array_sum(array_column($series, $k)), 2);
        $totalCollected = $sum('collected');
        $totalFees = $sum('fees');

        return response()->json([
            'months' => $months,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'series' => $series,
            'totals' => [
                'collected' => $totalCollected,
                'booked' => $sum('booked'),
                'fees' => $totalFees,
                // What actually reached the account after gateway fees.
                'net' => round($totalCollected - $totalFees, 2),
                'refunded' => $sum('refunded'),
                'payments' => (int) array_sum(array_column($series, 'payments')),
                'orders' => (int) array_sum(array_column($series, 'orders')),
            ],
        ]);
    }

    /**
     * One month of the overview, broken down: the orders won (sales closed), the
     * payments that landed, and a per-client roll-up. Same rules as monthly() —
     * orders by created_at excluding cancelled, cash by paid_at over succeeded
     * ledger rows with refunds netting out — so this page always adds up to its
     * overview row. `{month}` is YYYY-MM; malformed or future months are 404.
     */
    public function month(string $month): JsonResponse
    {
        $start = $this->parseMonth($month);
        $end = $start->copy()->endOfMonth();

        $orders = Order::query()
            ->with(['client:id,name,company', 'quotation.servicePackage'])
            ->whereNot('status', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->get();

        // Refunds are negative rows and stay in the list — a bad month should
        // read as one, not be tidied away.
        $payments = Payment::query()
            ->with(['client:id,name', 'order:id,order_number,created_at'])
            ->where('status', PaymentStatus::Succeeded)
            ->whereBetween('paid_at', [$start, $end])
            ->orderByDesc('paid_at')
            ->get();

        $collected = round((float) $payments->sum('amount_myr'), 2);
        $fees = round((float) $payments->sum('fee_myr'), 2);
        $next = $start->copy()->addMonth();

        return response()->json([
            'month' => $start->format('Y-m'),
            'label' => $start->format('M Y'),
            'prev' => $start->copy()->subMonth()->format('Y-m'),
            // The future isn't browsable — the current month is the last stop.
            'next' => $next->lte(now()->startOfMonth()) ? $next->format('Y-m') : null,
            'summary' => [
                'orders' => $orders->count(),
                'booked' => round((float) $orders->sum('final_amount_myr'), 2),
                'collected' => $collected,
                'fees' => $fees,
                'net' => round($collected - $fees, 2),
                'refunded' => round((float) $payments->where('amount_myr', '<', 0)->sum(fn ($p) => -$p->amount_myr), 2),
                'payments' => $payments->count(),
                // Still owed on THIS month's sales (cash-to-date, not just this month's).
                'outstanding' => round((float) $orders->sum(fn (Order $o) => $o->remaining_myr), 2),
            ],
            'orders' => $orders->map(fn (Order $o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'client' => $o->client ? ['id' => $o->client->id, 'name' => $o->client->name, 'company' => $o->client->company] : null,
                'label' => $this->orderLabel($o),
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'value' => round((float) $o->final_amount_myr, 2),
                'paid' => round((float) $o->amount_paid_myr, 2),
                'balance' => round((float) $o->remaining_myr, 2),
                'created_at' => $o->created_at?->toISOString(),
            ])->values(),
            'payments' => $payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'payment_number' => $p->payment_number,
                'paid_at' => $p->paid_at?->toISOString(),
                'client' => $p->client ? ['id' => $p->client->id, 'name' => $p->client->name] : null,
                'order_id' => $p->order_id,
                'order_number' => $p->order?->order_number,
                // Which month the sale was won — cash often trails the deposit month.
                'order_month' => $p->order?->created_at?->format('Y-m'),
                'type' => $p->type,
                'method' => $p->method,
                'amount' => round((float) $p->amount_myr, 2),
                'fee' => round((float) $p->fee_myr, 2),
            ])->values(),
            'clients' => $this->clientRollup($orders, $payments),
        ]);
    }

    /** YYYY-MM → start of that month, or 404 for a malformed / future month. */
    private function parseMonth(string $month): Carbon
    {
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month), 404);

        $start = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        abort_if($start->gt(now()->startOfMonth()), 404);

        return $start;
    }

    /**
     * What was sold: the quote's project title when it has one, else the catalog
     * package name — the same precedence the quotations list uses.
     */
    private function orderLabel(Order $order): string
    {
        $quotation = $order->quotation;
        if (! $quotation) {
            return 'Order';
        }

        $doc = is_array($quotation->document) ? $quotation->document : [];
        $project = $doc['project'] ?? ($doc['payload']['project'] ?? null);

        if (filled($project)) {
            return (string) $project;
        }

        return $quotation->servicePackage?->name
            ?? ($quotation->package_key ? Str::headline($quotation->package_key) : 'Custom');
    }

    /**
     * Booked vs collected per client for the month. A client who booked nothing
     * but paid off an older order still appears. Largest collected first.
     *
     * @return list<array{id: int, name: string, booked: float, collected: float, orders: int, payments: int}>
     */
    private function clientRollup($orders, $payments): array
    {
        $rows = [];

        foreach ($orders as $o) {
            $rows[$o->client_id] ??= ['id' => $o->client_id, 'name' => $o->client?->name ?? 'Unknown', 'booked' => 0.0, 'collected' => 0.0, 'orders' => 0, 'payments' => 0];
            $rows[$o->client_id]['booked'] += (float) $o->final_amount_myr;
            $rows[$o->client_id]['orders']++;
        }

        foreach ($payments as $p) {
            $rows[$p->client_id] ??= ['id' => $p->client_id, 'name' => $p->client?->name ?? 'Unknown', 'booked' => 0.0, 'collected' => 0.0, 'orders' => 0, 'payments' => 0];
            $rows[$p->client_id]['collected'] += (float) $p->amount_myr;
            $rows[$p->client_id]['payments']++;
        }

        $rows = array_map(fn ($r) => [...$r, 'booked' => round($r['booked'], 2), 'collected' => round($r['collected'], 2)], array_values($rows));
        usort($rows, fn ($a, $b) => [$b['collected'], $b['booked']] <=> [$a['collected'], $a['booked']]);

        return $rows;
    }
}
