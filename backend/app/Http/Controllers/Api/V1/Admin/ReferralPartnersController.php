<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferrerDetailResource;
use App\Http\Resources\ReferrerResource;
use App\Mail\PartnerPasscodeMail;
use App\Mail\PartnerResetLinkMail;
use App\Models\AccessToken;
use App\Models\ExternalAccount;
use App\Models\Referral;
use App\Models\Referrer;
use App\Services\Auth\AccessLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Mail;

/**
 * Referral-partner management for the /admin cockpit. Approving a pending referrer
 * flips them to active, mints the first passcode ONTO the linked external_accounts
 * row (created + linked here if the referrer has none — Task 9 moved credentials
 * off referral_partners), and emails it; reset regenerates a lost one the same
 * way. referral_partners.status stays the business status; the account's own
 * status (active/suspended) is what actually gates portal sign-in.
 *
 * Passcode discipline: the plaintext is generated here, handed straight to
 * PartnerPasscodeMail, and never returned in a response, rendered on a staff
 * screen, or written to a log. The audit trail records the action only.
 */
class ReferralPartnersController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Referrer::with('account')->withCount('referrals')->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return ReferrerResource::collection($query->paginate(20));
    }

    /**
     * Partner detail: profile + full referral history + derived earned/estimated
     * commission stats (converted-only earned; estimated across any referral with
     * an order reached via its quotation).
     */
    public function show(Referrer $referralPartner): ReferrerDetailResource
    {
        $referralPartner->load(['account', 'referrals' => fn ($q) => $q->with('quotation.order')->latest('created_at')]);

        $earned = 0.0;
        $estimated = 0.0;
        foreach ($referralPartner->referrals as $ref) {
            $order = $ref->orderViaQuotation();
            if (! $order) {
                continue;
            }
            $rate = $ref->effectivePct();
            $collected = (float) $order->amount_paid_myr;
            $contract = (float) $order->final_amount_myr;
            if ($ref->status === 'converted') {
                $earned += Referral::capCommission($collected * $rate / 100);
            }
            $estimated += Referral::pendingCommission($rate, $contract, $collected);
        }

        return (new ReferrerDetailResource($referralPartner))
            ->additional(['stats' => ['earned_myr' => round($earned, 2), 'estimated_myr' => round($estimated, 2), 'referrals_count' => $referralPartner->referrals->count()]]);
    }

    public function approve(Referrer $referralPartner, AccessLinks $links): JsonResponse
    {
        if ($referralPartner->isActive()) {
            return response()->json(['message' => 'This partner is already approved.'], 422);
        }

        $referralPartner->update(['status' => 'active']);

        $account = $this->syncAccount($referralPartner);

        $referralPartner->logActivity('referral_partner.approved', ['status' => 'active']);

        // Phase 2: a one-time set-up link — the partner chooses their own passcode.
        Mail::to($account->email, $referralPartner->name)
            ->send(new PartnerPasscodeMail($account, $links->issue($account, AccessToken::SETUP)));

        return response()->json(['message' => 'Partner approved. A set-up link has been emailed to them.']);
    }

    /**
     * Email the partner a one-time link (Phase 2): a set-up link if they never
     * chose a passcode, otherwise a 60-minute reset link. Their current
     * passcode keeps working until they use it.
     */
    public function resetPasscode(Referrer $referralPartner, AccessLinks $links): JsonResponse
    {
        if (! $referralPartner->isActive()) {
            return response()->json(['message' => 'Approve this partner before issuing a passcode.'], 422);
        }

        $account = $this->syncAccount($referralPartner);
        $setup = $account->password === null;

        $referralPartner->logActivity('referral_partner.passcode_reset');

        $url = $links->issue($account, $setup ? AccessToken::SETUP : AccessToken::RESET);
        Mail::to($account->email, $referralPartner->name)
            ->send($setup ? new PartnerPasscodeMail($account, $url) : new PartnerResetLinkMail($account, $url));

        return response()->json([
            'message' => $setup
                ? 'A new set-up link has been emailed to the partner.'
                : 'A passcode reset link has been emailed to the partner.',
        ]);
    }

    /**
     * Create or refresh the partner's login account: creates + links one (type
     * 'referrer') when the referrer has none, keeps the account email on the
     * profile email, and sets its status from the referrer's business status
     * (active ↔ suspended). The passcode is never set here — the partner
     * chooses it through a link.
     */
    private function syncAccount(Referrer $referralPartner): ExternalAccount
    {
        $status = $referralPartner->isActive() ? 'active' : 'suspended';

        $account = $referralPartner->account;

        if ($account) {
            $account->update([
                'email' => $referralPartner->email,
                'status' => $status,
            ]);

            return $account;
        }

        $account = ExternalAccount::create([
            'type' => 'referrer',
            'email' => $referralPartner->email,
            'status' => $status,
        ]);

        $referralPartner->forceFill(['external_account_id' => $account->id])->saveQuietly();
        $referralPartner->setRelation('account', $account);

        return $account;
    }
}
