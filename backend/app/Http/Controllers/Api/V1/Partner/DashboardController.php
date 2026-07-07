<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Models\ExternalAccount;
use App\Models\Referral;
use App\Models\Referrer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The referrer's own read-mostly portal (referrer-only — the route group's
 * `partner.type:referrer` middleware 403s investor tokens). Every query is scoped
 * to the referrer profile behind the authenticated ExternalAccount — a partner can
 * only ever see their own leads and earnings. Commission is DERIVED here (pct ×
 * collected order value), never stored, and payout stays manual (Section 3: no
 * funds-transfer engine is built).
 */
class DashboardController extends Controller
{
    /**
     * Resolve the referrer profile behind the authenticated account. The type
     * middleware guarantees a referrer-typed token; a missing profile row would
     * be a data error, surfaced as 403 rather than a 500.
     */
    private function referrerFor(Request $request): Referrer
    {
        /** @var ExternalAccount $account */
        $account = $request->user();
        $referrer = $account->referrer;

        if (! $referrer) {
            throw new HttpException(403, 'No referrer profile is linked to this account.');
        }

        return $referrer;
    }

    public function index(Request $request): JsonResponse
    {
        $referrer = $this->referrerFor($request);

        $referrals = $referrer->referrals()->with('quotation.order')->latest('created_at')->get();
        $earned = 0.0;
        $estimated = 0.0;

        $rows = $referrals->map(function (Referral $referral) use (&$earned, &$estimated) {
            $order = $referral->orderViaQuotation();
            $rate = $referral->effectivePct();
            $collected = (float) ($order->amount_paid_myr ?? 0);
            $contract = (float) ($order->final_amount_myr ?? 0);

            $earnedForRef = $referral->status === 'converted' ? round($collected * $rate / 100, 2) : 0.0;
            $earned += $earnedForRef;
            $estimated += $order ? max(0, round(($contract - $collected) * $rate / 100, 2)) : 0.0;

            return [
                'id' => $referral->id,
                'business_name' => $referral->business_name,
                'status' => $referral->status,
                'commission_pct' => $rate,
                'has_order' => (bool) $order,
                'earned_myr' => $referral->status === 'converted' ? $earnedForRef : null,
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
                'estimated_myr' => round($estimated, 2),
                'referrals_count' => $referrals->count(),
            ],
            'ref_link' => rtrim((string) config('services.frontend.public_url'), '/').'/?ref='.$referrer->code,
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

        $referrer = $this->referrerFor($request);
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
