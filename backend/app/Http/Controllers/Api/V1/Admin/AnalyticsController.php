<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\EntityLike;
use App\Models\PageView;
use App\Models\Project;
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
