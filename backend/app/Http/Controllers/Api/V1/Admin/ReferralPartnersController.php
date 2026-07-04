<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferrerDetailResource;
use App\Http\Resources\ReferrerResource;
use App\Mail\PartnerPasscodeMail;
use App\Models\ExternalAccount;
use App\Models\Referrer;
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
                $earned += round($collected * $rate / 100, 2);
            }
            $estimated += max(0, round(($contract - $collected) * $rate / 100, 2));
        }

        return (new ReferrerDetailResource($referralPartner))
            ->additional(['stats' => ['earned_myr' => round($earned, 2), 'estimated_myr' => round($estimated, 2), 'referrals_count' => $referralPartner->referrals->count()]]);
    }

    /**
     * Approve a pending referrer: flip to active, mint the first passcode onto the
     * linked external account (created here if none), email it. This is the ONLY
     * moment a brand-new referrer's portal credentials are created.
     */
    public function approve(Referrer $referralPartner): JsonResponse
    {
        if ($referralPartner->isActive()) {
            return response()->json(['message' => 'This partner is already approved.'], 422);
        }

        $referralPartner->update(['status' => 'active']);

        $passcode = ExternalAccount::makePasscode();
        $account = $this->syncAccount($referralPartner, $passcode);

        // Audit the action — NEVER the passcode.
        $referralPartner->logActivity('referral_partner.approved', ['status' => 'active']);

        Mail::to($account->email, $referralPartner->name)
            ->send(new PartnerPasscodeMail($account, $passcode));

        return response()->json(['message' => 'Partner approved. A passcode has been emailed to them.']);
    }

    /**
     * Staff-initiated passcode reset for an already-active partner: regenerate +
     * email. (A backfilled/migrated active partner with no account yet gets their
     * first one here — the account is created and linked on the fly.)
     */
    public function resetPasscode(Referrer $referralPartner): JsonResponse
    {
        if (! $referralPartner->isActive()) {
            return response()->json(['message' => 'Approve this partner before issuing a passcode.'], 422);
        }

        $passcode = ExternalAccount::makePasscode();
        $account = $this->syncAccount($referralPartner, $passcode);

        $referralPartner->logActivity('referral_partner.passcode_reset');

        Mail::to($account->email, $referralPartner->name)
            ->send(new PartnerPasscodeMail($account, $passcode));

        return response()->json(['message' => 'A new passcode has been emailed to the partner.']);
    }

    /**
     * Create-or-update the referrer's portal identity: write the new passcode
     * (hashed by the model cast) onto the linked external_accounts row, creating
     * + linking one (type 'referrer') when the referrer has none. Account status
     * follows the referrer's business status (active ↔ suspended) at each mint,
     * and the account email tracks the profile email.
     */
    private function syncAccount(Referrer $referralPartner, string $passcode): ExternalAccount
    {
        $status = $referralPartner->isActive() ? 'active' : 'suspended';

        $account = $referralPartner->account;

        if ($account) {
            $account->update([
                'email' => $referralPartner->email,
                'password' => $passcode,
                'status' => $status,
            ]);

            return $account;
        }

        $account = ExternalAccount::create([
            'type' => 'referrer',
            'email' => $referralPartner->email,
            'password' => $passcode,
            'status' => $status,
        ]);

        $referralPartner->forceFill(['external_account_id' => $account->id])->saveQuietly();
        $referralPartner->setRelation('account', $account);

        return $account;
    }
}
