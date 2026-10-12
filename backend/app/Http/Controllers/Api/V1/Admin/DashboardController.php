<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\PageView;
use App\Models\Quotation;
use App\Models\Referral;
use App\Services\Quoting\QuotationIndexQuery;
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
            // One row per quotation FAMILY, however many revisions it has — so a
            // revised quote counts once everywhere below.
            'quotations_total' => Quotation::count(),
            'quotations_draft' => Quotation::where('status', 'draft')->count(),
            // Conversion: families whose current revision is accepted, over every
            // family that has gone out to a client (sent / accepted / rejected / expired).
            'quotations_accepted' => $accepted = Quotation::where('status', 'accepted')->count(),
            'quotations_decided' => $decided = Quotation::whereIn('status', ['sent', 'accepted', 'rejected', 'expired'])->count(),
            'quotations_conversion_pct' => $decided > 0 ? round($accepted / $decided * 100, 1) : null,
            'quotations_revised' => (new QuotationIndexQuery(revisedAfterAcceptance: true))->builder()->count(),
            'referrals_active' => Referral::where('status', '!=', 'rejected')->count(),
            'orders_total' => Order::count(),
            // Sales inquiries only; leads from /collaborations are counted apart
            // (they live under Partners › Collaborations).
            'inquiries_new' => Inquiry::where('status', 'new')->whereNotIn('origin', Inquiry::COLLAB_ORIGINS)->count(),
            'collab_enquiries_new' => Inquiry::where('status', 'new')->whereIn('origin', Inquiry::COLLAB_ORIGINS)->count(),
            // Same window as analytics overview range=7 (subDays(range - 1)).
            'views_7d' => PageView::where('viewed_at', '>=', now()->subDays(6)->startOfDay())->count(),
        ]);

        return response()->json($data);
    }
}
