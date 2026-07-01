<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferralTeamResource;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Referral-programme management for the /team workspace (the marketer's surface).
 * Read + lifecycle-status only: linking a referral to an order and the commission
 * email are money operations and stay in the cockpit Admin\ReferralsController.
 * Responses use ReferralTeamResource, which carries no financial figures.
 */
class ReferralsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Referral::latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('referrer_name', 'like', "%{$search}%")
                    ->orWhere('referrer_email', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%")
                    ->orWhere('business_email', 'like', "%{$search}%");
            });
        }

        return ReferralTeamResource::collection($query->paginate(20));
    }

    public function show(Referral $referral): ReferralTeamResource
    {
        return new ReferralTeamResource($referral);
    }

    public function updateStatus(Request $request, Referral $referral): JsonResponse
    {
        // 'converted' is a lifecycle flag the marketer may set; attaching the order
        // (and thus the commission money) stays a cockpit action.
        $request->validate([
            'status' => ['required', 'in:new,contacted,qualified,converted,rejected'],
        ]);

        $from = $referral->status;
        $referral->update(['status' => $request->status]);
        $referral->logActivity('referral.status', ['from' => $from, 'to' => $referral->status]);

        return response()->json(['message' => 'Status updated.', 'status' => $referral->status]);
    }
}
