<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\EntityLike;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\PageView;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Referrer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * Traffic overview for the admin analytics page. Page-view metrics over a
     * 7- or 30-day window: total + unique visitors, a per-day series for the
     * chart, and top paths / referrers. (Likes, service interest and the quote
     * funnel are added in later slices.)
     */
    public function overview(Request $request): JsonResponse
    {
        $range = $request->query('range') === '30d' ? 30 : 7;
        $since = now()->subDays($range - 1)->startOfDay();

        $base = fn () => PageView::where('viewed_at', '>=', $since);

        $total = $base()->count();
        $unique = $base()->distinct('ip_hash')->count('ip_hash');

        // Per-day counts → dense series (zero-filled) oldest → newest.
        $byDay = $base()
            ->selectRaw('DATE(viewed_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $series = [];
        for ($i = $range - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $series[] = ['date' => $day, 'count' => (int) ($byDay[$day] ?? 0)];
        }

        $topPaths = $base()
            ->selectRaw('path, COUNT(*) as c')
            ->groupBy('path')
            ->orderByDesc('c')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['path' => $r->path, 'count' => (int) $r->c]);

        $topReferrers = $base()
            ->whereNotNull('referrer')
            ->where('referrer', '!=', '')
            ->selectRaw('referrer, COUNT(*) as c')
            ->groupBy('referrer')
            ->orderByDesc('c')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['referrer' => $r->referrer, 'count' => (int) $r->c]);

        return response()->json([
            'range' => $range,
            'views' => [
                'total' => $total,
                'unique' => $unique,
                'series' => $series,
            ],
            'topPaths' => $topPaths,
            'topReferrers' => $topReferrers,
            'topLikedProjects' => $this->topLikedProjects(),
        ]);
    }

    /**
     * Attribution — where collected revenue comes from. Traces succeeded payments
     * up the chain (payment → order → quotation → originating inquiry) and reports
     * contracted vs collected by inquiry source, plus a referrer roll-up traced
     * through the normalized chain (payment → order → quotation.referral_partner_id
     * → referral_partners). Commission stays derived, never stored. Payments/orders
     * whose quotation carries no referral_partner_id (or has no quotation at all)
     * bucket under "Public" — organic, unattributed revenue.
     */
    public function attribution(Request $request): JsonResponse
    {
        // Collected = signed SUM over succeeded rows (refunds are negative, so they
        // net out), grouped per order.
        $collectedByOrder = Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->groupBy('order_id')
            ->selectRaw('order_id, SUM(amount_myr) as collected')
            ->pluck('collected', 'order_id');

        $orders = Order::query()->get(['id', 'quotation_id', 'final_amount_myr']);

        // quotation_id → originating inquiry source (web / referral / other).
        $sourceByQuotation = Inquiry::query()
            ->whereNotNull('quotation_id')
            ->pluck('source', 'quotation_id');

        $bySource = [];
        $totalContracted = 0.0;
        $totalCollected = 0.0;

        foreach ($orders as $order) {
            // No originating inquiry → a directly-built or public quote.
            $source = $sourceByQuotation[$order->quotation_id] ?? 'direct';
            $contracted = (float) $order->final_amount_myr;
            $collected = (float) ($collectedByOrder[$order->id] ?? 0);

            $totalContracted += $contracted;
            $totalCollected += $collected;

            $bySource[$source] ??= ['source' => $source, 'orders' => 0, 'contracted' => 0.0, 'collected' => 0.0];
            $bySource[$source]['orders']++;
            $bySource[$source]['contracted'] += $contracted;
            $bySource[$source]['collected'] += $collected;
        }

        // quotation_id → referral_partner_id, for orders reached via the normalized
        // chain (payment → order → quotation.referral_partner_id).
        $referrerByQuotation = Quotation::query()
            ->whereNotNull('referral_partner_id')
            ->pluck('referral_partner_id', 'id');

        $referrers = Referrer::query()->get(['id', 'name', 'email', 'commission_pct'])->keyBy('id');

        // Roll up contracted/collected per order under its referrer (or the
        // "Public" bucket when the quotation carries no referral_partner_id, or
        // the order has no quotation at all).
        $rollup = [];
        foreach ($orders as $order) {
            $partnerId = $referrerByQuotation[$order->quotation_id] ?? null;
            $key = $partnerId ?? 'public';

            $contracted = (float) $order->final_amount_myr;
            $collected = (float) ($collectedByOrder[$order->id] ?? 0);

            $rollup[$key] ??= ['partner_id' => $partnerId, 'orders' => 0, 'contracted' => 0.0, 'collected' => 0.0];
            $rollup[$key]['orders']++;
            $rollup[$key]['contracted'] += $contracted;
            $rollup[$key]['collected'] += $collected;
        }

        $byReferrer = collect($rollup)->map(function ($row) use ($referrers) {
            $referrer = $row['partner_id'] !== null ? $referrers->get($row['partner_id']) : null;
            $pct = $referrer ? (int) $referrer->commission_pct : 0;
            $collected = $row['collected'];

            return [
                'referrer' => $referrer?->name ?? 'Public',
                'email' => $referrer?->email,
                'referrals' => $row['orders'],
                'commission_pct' => $pct,
                'contracted' => round($row['contracted'], 2),
                'collected' => round($collected, 2),
                // Derived, never stored — payout stays manual (plan §3).
                'commission_est' => round($collected * $pct / 100, 2),
            ];
        })
            ->sortByDesc('collected')
            ->values();

        return response()->json([
            'totals' => [
                'contracted' => round($totalContracted, 2),
                'collected' => round($totalCollected, 2),
            ],
            'bySource' => collect($bySource)->sortByDesc('collected')->values(),
            'byReferrer' => $byReferrer,
        ]);
    }

    /** All-time most-liked projects (likes accumulate, so not range-bound). */
    private function topLikedProjects(): array
    {
        $rows = EntityLike::where('entity_type', 'project')
            ->selectRaw('entity_id, COUNT(*) as c')
            ->groupBy('entity_id')
            ->orderByDesc('c')
            ->limit(8)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = Project::whereIn('id', $rows->pluck('entity_id'))->pluck('name', 'id');

        return $rows->map(fn ($r) => [
            'id' => (int) $r->entity_id,
            'name' => $names[$r->entity_id] ?? "Project #{$r->entity_id}",
            'likes' => (int) $r->c,
        ])->values()->all();
    }
}
