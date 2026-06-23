<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReferralRequest;
use App\Mail\ReferralReceivedMail;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class ReferralController extends Controller
{
    public function store(StoreReferralRequest $request): JsonResponse
    {
        $tier = $request->input('relationship_tier');

        $referral = Referral::create([
            'referrer_name' => $request->input('referrer_name'),
            'referrer_email' => $request->input('referrer_email'),
            'referrer_phone' => $request->input('referrer_phone'),
            'business_name' => $request->input('business_name'),
            'business_contact_name' => $request->input('business_contact_name'),
            'business_email' => $request->input('business_email'),
            'business_phone' => $request->input('business_phone'),
            'relationship_tier' => $tier,
            'commission_tier_pct' => Referral::commissionPctFor($tier),
            'notes' => $request->input('notes'),
            'status' => 'new',
            'agreed_terms' => $request->boolean('agreed_terms'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Queued acknowledgement back to the person who made the referral.
        Mail::to($referral->referrer_email, $referral->referrer_name)->send(new ReferralReceivedMail($referral));

        return response()->json([
            'data' => [
                'id' => $referral->id,
                'relationship_tier' => $referral->relationship_tier,
                'commission_tier_pct' => $referral->commission_tier_pct,
            ],
            'message' => 'Referral received. We\'ll reach out to the business and keep you posted.',
        ], 201);
    }
}
