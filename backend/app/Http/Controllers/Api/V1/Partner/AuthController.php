<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Mail\PartnerPasscodeMail;
use App\Mail\PartnerResetRequestedMail;
use App\Models\ExternalAccount;
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
     * Self-service passcode reset for both partner kinds. A correct, active email
     * auto-issues a fresh passcode straight to the partner's own inbox — and
     * notifies the founder that it happened. The response is identical whether or
     * not the email matches, so this never reveals who holds an account. (Abuse
     * is bounded by the login-throttle group this route sits in.)
     */
    public function forgotPasscode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $account = ExternalAccount::where('email', $data['email'])->where('status', 'active')->first();

        if ($account) {
            $passcode = ExternalAccount::makePasscode();

            $account->update(['password' => $passcode]);

            // Audit on the referrer profile (it carries updated_by + the activity
            // trail); investor accounts have no audited profile yet.
            $account->referrer?->logActivity('referral_partner.passcode_self_reset');

            // The new passcode only ever lands in the partner's own inbox.
            Mail::to($account->email, $account->displayName())->send(new PartnerPasscodeMail($account, $passcode));

            // Keep the founder informed (heads-up; the reset already happened).
            $adminEmail = config('services.admin.email') ?: config('mail.from.address');
            if ($adminEmail) {
                Mail::to($adminEmail)->send(new PartnerResetRequestedMail($account));
            }
        }

        return response()->json([
            'message' => 'If that email matches an active partner account, a new passcode has been sent to it.',
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
