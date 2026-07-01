<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\Referrer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The partner's own read-mostly portal. Every query is scoped to the authenticated
 * referrer ($request->user()) — a partner can only ever see their own leads and
 * earnings. Commission is DERIVED here (pct × collected order value), never stored,
 * and payout stays manual (Section 3: no funds-transfer engine is built).
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Referrer $referrer */
        $referrer = $request->user();

        $referrals = $referrer->referrals()->with('order')->latest('created_at')->get();

        $earned = 0.0;   // commission on money actually COLLECTED (from payments)
        $pending = 0.0;  // commission on the rest of the contracted value

        // Commission is derived PER REFERRAL — each lead carries its own rate from
        // the relationship tier it was referred under (cold 5 / warm 10 / closed 15),
        // not a single partner-wide percentage.
        $rows = $referrals->map(function (Referral $referral) use (&$earned, &$pending) {
            $order = $referral->order;
            $pct = (int) $referral->commission_tier_pct;

            $collected = (float) ($order->amount_paid_myr ?? 0);
            $contract = (float) ($order->final_amount_myr ?? 0);

            $earnedForRef = round($collected * $pct / 100, 2);
            $contractCommission = round($contract * $pct / 100, 2);
            $pendingForRef = max(0, round($contractCommission - $earnedForRef, 2));

            $earned += $earnedForRef;
            $pending += $pendingForRef;

            return [
                'id' => $referral->id,
                'business_name' => $referral->business_name,
                'status' => $referral->status,
                'relationship_tier' => $referral->relationship_tier,
                'commission_pct' => $pct,
                'converted' => (bool) $referral->linked_order_id,
                'earned_myr' => $order ? $earnedForRef : null,
                'created_at' => $referral->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'partner' => [
                'name' => $referrer->name,
                'code' => $referrer->code,
                'relationship_tier' => $referrer->relationship_tier,
                // The bands (tier → %), so the portal can explain the varying rate
                // instead of quoting one fixed number.
                'commission_tiers' => Referral::COMMISSION_TIERS,
            ],
            'stats' => [
                'earned_myr' => round($earned, 2),
                'pending_myr' => round($pending, 2),
                'referrals_count' => $referrals->count(),
            ],
            'ref_link' => rtrim((string) config('services.frontend.url'), '/').'/?ref='.$referrer->code,
            'referrals' => $rows,
        ]);
    }

    /**
     * Context-aware "refer another company" — the account is already bound, so the
     * referrer identity comes from the token, not the form. No re-onboarding, and
     * status is untouched (they stay active).
     */
    public function storeReferral(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'min:2', 'max:200'],
            'business_contact_name' => ['nullable', 'string', 'max:150'],
            'business_email' => ['nullable', 'email:rfc', 'max:200'],
            'business_phone' => ['nullable', 'string', 'max:30'],
            'relationship_tier' => ['required', 'string', 'in:cold,warm,closed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var Referrer $referrer */
        $referrer = $request->user();
        $tier = $data['relationship_tier'];

        $referral = Referral::create([
            'referral_partner_id' => $referrer->id,
            'referrer_name' => $referrer->name,
            'referrer_email' => $referrer->email,
            'referrer_phone' => $referrer->phone,
            'business_name' => $data['business_name'],
            'business_contact_name' => $data['business_contact_name'] ?? null,
            'business_email' => $data['business_email'] ?? null,
            'business_phone' => $data['business_phone'] ?? null,
            'relationship_tier' => $tier,
            'commission_tier_pct' => Referral::commissionPctFor($tier),
            'notes' => $data['notes'] ?? null,
            'status' => 'new',
            'agreed_terms' => true, // agreed at onboarding; already an active partner
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Referral submitted. We\'ll reach out and keep you posted.',
            'id' => $referral->id,
        ], 201);
    }
}
