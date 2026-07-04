<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReferralRequest;
use App\Mail\ReferralReceivedMail;
use App\Models\Referral;
use App\Models\Referrer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class ReferralController extends Controller
{
    /**
     * Public onboarding submit (approve-first). One POST does two things:
     *   1. creates or reuses the Referrer (matched on email) as status = pending;
     *   2. creates the referred company as a Referral, visible immediately.
     *
     * NO passcode is issued here — the passcode email fires only when a marketer
     * approves the referrer (Team\ReferralPartnersController@approve). A returning
     * active referrer stays active; this never re-onboards them.
     */
    public function store(StoreReferralRequest $request): JsonResponse
    {
        $tier = $request->input('relationship_tier');

        $referrer = Referrer::firstOrNew(['email' => $request->input('referrer_email')]);

        if (! $referrer->exists) {
            $referrer->fill([
                'code' => Referrer::makeUniqueCode(),
                'name' => $request->input('referrer_name'),
                'phone' => $request->input('referrer_phone'),
                'relationship_tier' => $tier,
                'commission_pct' => Referrer::commissionPctFor($tier),
                'agreed_terms' => $request->boolean('agreed_terms'),
                'status' => 'pending',
            ])->save();
        }

        $referral = Referral::create([
            'referral_partner_id' => $referrer->id,
            'referrer_name' => $referrer->name,
            'referrer_email' => $referrer->email,
            'referrer_phone' => $referrer->phone,
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

        // Queued acknowledgement back to the person who made the referral. This is
        // NOT the passcode email — that only follows a marketer approval.
        Mail::to($referrer->email, $referrer->name)->send(new ReferralReceivedMail($referral));

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
