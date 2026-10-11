<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Mail\PartnerPasscodeMail;
use App\Mail\PartnerResetLinkMail;
use App\Models\AccessToken;
use App\Models\ExternalAccount;
use App\Services\Auth\AccessLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Partner-portal auth — the third surface, on the isolated `external` guard
 * (external_accounts: the unified referrer + investor identity, Task 9). Pure
 * bearer tokens (no stateful cookie/CSRF): a token minted here is an
 * ExternalAccount token, which the `sanctum` guard (provider = users) behind
 * /admin + /team rejects. Only an active account with an issued passcode can
 * sign in — a referrer profile that was never approved has no account at all.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'passcode' => ['required', 'string'],
        ]);

        $account = ExternalAccount::where('email', $credentials['email'])->first();

        // Uniform rejection for: unknown email, suspended, no passcode issued,
        // or wrong passcode — never leak which condition failed.
        if (! $account
            || ! $account->isActive()
            || ! $account->password
            || ! Hash::check($credentials['passcode'], $account->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $account->forceFill(['last_login_at' => now()])->saveQuietly();

        // Read-scoped ability; global sanctum expiration (SANCTUM_EXPIRATION_MINUTES)
        // gives every partner token an expiry.
        $token = $account->createToken('partner-portal', ['partner'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'partner' => $this->present($account),
        ]);
    }

    /**
     * Self-service passcode reset (Phase 2): a matching active account gets a
     * 60-minute reset link. Nothing changes until the partner uses it, so a
     * stranger who knows their email can't lock them out. Always a generic
     * response, and a few links an hour per address at most. The owner hears
     * about it once the passcode actually changes.
     */
    public function forgotPasscode(Request $request, AccessLinks $links): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $account = $links->allowForgotRequest('partner', $data['email'])
            ? ExternalAccount::where('email', $data['email'])->where('status', 'active')->first()
            : null;

        if ($account) {
            $account->referrer?->logActivity('referral_partner.passcode_reset_requested');

            $purpose = $account->password === null ? AccessToken::SETUP : AccessToken::RESET;
            $url = $links->issue($account, $purpose);
            Mail::to($account->email, $account->displayName())->send(
                $purpose === AccessToken::SETUP ? new PartnerPasscodeMail($account, $url) : new PartnerResetLinkMail($account, $url),
            );
        }

        return response()->json([
            'message' => "If that email matches an active partner account, we've sent it a link to choose a new passcode.",
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->present($request->user()));
    }

    /**
     * Type-aware presentation: {type, email, profile} where profile carries the
     * referrer fields (code/tier/commission) or the investor fields (company).
     */
    private function present(ExternalAccount $account): array
    {
        $profile = null;

        if ($account->isReferrer() && $account->referrer) {
            $referrer = $account->referrer;
            $profile = [
                'id' => $referrer->id,
                'name' => $referrer->name,
                'code' => $referrer->code,
                'relationship_tier' => $referrer->relationship_tier,
                'commission_pct' => $referrer->commission_pct,
            ];
        } elseif ($account->isInvestor() && $account->investor) {
            $investor = $account->investor;
            $profile = [
                'id' => $investor->id,
                'name' => $investor->name,
                'company' => $investor->company,
            ];
        }

        return [
            'id' => $account->id,
            'type' => $account->type,
            'email' => $account->email,
            'profile' => $profile,
        ];
    }
}
