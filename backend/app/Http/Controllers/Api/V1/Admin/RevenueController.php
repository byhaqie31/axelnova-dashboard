<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
