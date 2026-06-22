<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferralResource;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReferralsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Referral::with('order')->latest('created_at');

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

        return ReferralResource::collection($query->paginate(20));
    }

    public function show(Referral $referral): ReferralResource
    {
        $referral->load('order');

        return new ReferralResource($referral);
    }

    public function updateStatus(Request $request, Referral $referral): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:new,contacted,qualified,converted,rejected'],
        ]);

        $referral->update(['status' => $request->status]);

        return response()->json(['message' => 'Status updated.', 'status' => $referral->status]);
    }

    public function linkOrder(Request $request, Referral $referral): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $referral->update([
            'linked_order_id' => $request->integer('order_id'),
            'status' => 'converted',
        ]);

        $referral->load('order');

        return response()->json([
            'message' => 'Referral linked to order and marked converted.',
            'referral' => new ReferralResource($referral),
        ]);
    }
}
