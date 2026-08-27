<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\PageView;
use App\Models\Quotation;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * The dashboard landing payload. One request of six COUNTs replaces the old
 * pattern of fetching five full paginated lists (plus the analytics overview)
 * just to read their `meta.total`s.
 */
class DashboardController extends Controller
{
    public function counts(): JsonResponse
    {
        $data = Cache::remember('admin:dashboard:counts', 60, fn () => [
            'quotations_total' => Quotation::count(),
            'quotations_draft' => Quotation::where('status', 'draft')->count(),
            'referrals_active' => Referral::where('status', '!=', 'rejected')->count(),
            'orders_total' => Order::count(),
            'inquiries_new' => Inquiry::where('status', 'new')->count(),
            // Same window as analytics overview range=7 (subDays(range - 1)).
            'views_7d' => PageView::where('viewed_at', '>=', now()->subDays(6)->startOfDay())->count(),
        ]);

        return response()->json($data);
    }
}
