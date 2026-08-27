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
use App\Models\Referrer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    /**
     * Traffic overview for the admin analytics page. Page-view metrics over a
     * 7-, 30- or 90-day window: total + unique visitors, a per-day series for the
     * chart, and top paths / referrers. (Likes, service interest and the quote
     * funnel are added in later slices.)
     */
    public function overview(Request $request): JsonResponse
    {
        // Whitelist, so an unknown value falls back to 7 rather than letting a
        // caller pick an arbitrary window size.
        $range = match ($request->query('range')) {
            '90d' => 90,
            '30d' => 30,
            default => 7,
        };

        // Five aggregates over page_views (the highest-write table) recompute an
        // identical answer for every viewer — cache the whole payload briefly.
        $payload = Cache::remember(
            "admin:analytics:overview:{$range}",
            120,
            fn () => $this->buildOverview($range),
        );

        return response()->json($payload);
    }

    /** @return array<string, mixed> */
    private function buildOverview(int $range): array
    {
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

        return [
            'range' => $range,
            'views' => [
                'total' => $total,
                'unique' => $unique,
                'series' => $series,
            ],
            'topPaths' => $topPaths,
            'topReferrers' => $topReferrers,
            'topLikedProjects' => $this->topLikedProjects(),
        ];
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
        // Grouped in SQL (this used to load every order/payment/quotation/inquiry
        // into PHP), and cached briefly — the roll-up is identical for every viewer.
        return response()->json(Cache::remember('admin:analytics:attribution', 300, fn () => $this->buildAttribution()));
    }

    /** @return array<string, mixed> */
    private function buildAttribution(): array
    {
        // Collected = signed SUM over succeeded rows (refunds are negative, so they
        // net out), grouped per order.
        $collectedSub = Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->groupBy('order_id')
            ->selectRaw('order_id, SUM(amount_myr) as collected');

        // One originating-inquiry source per quotation (web / referral / other).
        $sourceSub = Inquiry::query()
            ->whereNotNull('quotation_id')
            ->groupBy('quotation_id')
            ->selectRaw('quotation_id, MAX(source) as source');

        // Contracted vs collected by inquiry source. No originating inquiry →
        // a directly-built or public quote, bucketed under 'direct'.
        $bySourceRows = Order::query()
            ->leftJoinSub($collectedSub, 'p', 'p.order_id', '=', 'orders.id')
            ->leftJoinSub($sourceSub, 'i', 'i.quotation_id', '=', 'orders.quotation_id')
            ->groupByRaw("COALESCE(i.source, 'direct')")
            ->selectRaw("COALESCE(i.source, 'direct') as source")
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(orders.final_amount_myr), 0) as contracted')
            ->selectRaw('COALESCE(SUM(p.collected), 0) as collected')
            ->get();

        $bySource = $bySourceRows->map(fn ($r) => [
            'source' => $r->source,
            'orders' => (int) $r->orders_count,
            'contracted' => (float) $r->contracted,
            'collected' => (float) $r->collected,
        ]);

        // Roll up contracted/collected per order under its referrer via the
        // normalized chain (order → quotation.referral_partner_id), or the
        // "Public" bucket when the quotation carries no partner (or the order
        // has no quotation at all).
        $rollupRows = Order::query()
            ->leftJoinSub($collectedSub, 'p', 'p.order_id', '=', 'orders.id')
            ->leftJoin('quotations as q', fn ($join) => $join
                ->on('q.id', '=', 'orders.quotation_id')
                ->whereNull('q.deleted_at'))
            ->groupBy('q.referral_partner_id')
            ->selectRaw('q.referral_partner_id as partner_id')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(orders.final_amount_myr), 0) as contracted')
            ->selectRaw('COALESCE(SUM(p.collected), 0) as collected')
            ->get();

        $referrers = Referrer::query()
            ->whereIn('id', $rollupRows->pluck('partner_id')->filter())
            ->get(['id', 'name', 'email', 'commission_pct'])
            ->keyBy('id');

        $byReferrer = $rollupRows->map(function ($row) use ($referrers) {
            $referrer = $row->partner_id !== null ? $referrers->get($row->partner_id) : null;
            $pct = $referrer ? (int) $referrer->commission_pct : 0;
            $collected = (float) $row->collected;

            return [
                'referrer' => $referrer?->name ?? 'Public',
                'email' => $referrer?->email,
                'referrals' => (int) $row->orders_count,
                'commission_pct' => $pct,
                'contracted' => round((float) $row->contracted, 2),
                'collected' => round($collected, 2),
                // Derived, never stored — payout stays manual (plan §3).
                'commission_est' => round($collected * $pct / 100, 2),
            ];
        })
            ->sortByDesc('collected')
            ->values();

        return [
            'totals' => [
                'contracted' => round((float) $bySourceRows->sum('contracted'), 2),
                'collected' => round((float) $bySourceRows->sum('collected'), 2),
            ],
            'bySource' => $bySource->sortByDesc('collected')->values(),
            'byReferrer' => $byReferrer,
        ];
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
