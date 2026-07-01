<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferralResource;
use App\Mail\ReferralCommissionMail;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Mail;

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

        $from = $referral->status;
        $referral->update(['status' => $request->status]);
        $referral->logActivity('referral.status', ['from' => $from, 'to' => $referral->status]);

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
        $referral->logActivity('referral.linked_order', ['order_id' => $referral->linked_order_id]);

        $referral->load('order');

        return response()->json([
            'message' => 'Referral linked to order and marked converted.',
            'referral' => new ReferralResource($referral),
        ]);
    }

    /**
     * Email the referrer their estimated commission and ask them to reply with
     * bank details. Only valid once converted with a priced order linked.
     */
    public function sendCommissionEmail(Referral $referral): JsonResponse
    {
        $referral->load('order');

        if ($referral->status !== 'converted' || ! $referral->order) {
            return response()->json(['message' => 'Link the referral to an order before requesting commission details.'], 422);
        }

        $commission = $referral->commissionAmount();
        if (! $commission) {
            return response()->json(['message' => 'The linked order has no final amount yet — set it before emailing.'], 422);
        }

        Mail::to($referral->referrer_email, $referral->referrer_name)
            ->send(new ReferralCommissionMail($referral, $commission));

        $referral->update(['commission_email_sent_at' => now()]);

        return response()->json([
            'message' => 'Commission email sent to the referrer.',
            'referral' => new ReferralResource($referral),
        ]);
    }
}
