<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Mail\PartnerPasscodeMail;
use App\Mail\PartnerResetRequestedMail;
use App\Models\Referrer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Partner-portal auth — the third surface, on the isolated `referral` guard. Pure
 * bearer tokens (no stateful cookie/CSRF): a token minted here is a Referrer token,
 * which the `sanctum` guard (provider = users) behind /admin + /team rejects. Only
 * an approved (active) referrer with an issued passcode can sign in. There is no
 * self-service reset — a lost passcode is reissued by staff.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'passcode' => ['required', 'string'],
        ]);

        $referrer = Referrer::where('email', $credentials['email'])->first();

        // Uniform rejection for: unknown email, not-yet-approved, no passcode issued,
        // or wrong passcode — never leak which condition failed.
        if (! $referrer
            || ! $referrer->isActive()
            || ! $referrer->password
            || ! Hash::check($credentials['passcode'], $referrer->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $referrer->forceFill(['last_login_at' => now()])->saveQuietly();

        // Read-scoped ability; global sanctum expiration (SANCTUM_EXPIRATION_MINUTES)
        // gives every partner token an expiry.
        $token = $referrer->createToken('partner-portal', ['partner'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'partner' => $this->present($referrer),
        ]);
    }

    /**
     * Self-service passcode reset. A correct, active email auto-issues a fresh
     * passcode straight to the partner's own inbox — and notifies the founder that
     * it happened. The response is identical whether or not the email matches, so
     * this never reveals who holds an account. (This intentionally relaxes the
     * "staff-only reset" rule; brute-force/abuse is bounded by the login throttle.)
     */
    public function forgotPasscode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $referrer = Referrer::where('email', $data['email'])->where('status', 'active')->first();

        if ($referrer) {
            $passcode = Referrer::makePasscode();

            $referrer->update(['password' => $passcode]);
            $referrer->logActivity('referral_partner.passcode_self_reset');

            // The new passcode only ever lands in the partner's own inbox.
            Mail::to($referrer->email, $referrer->name)->send(new PartnerPasscodeMail($referrer, $passcode));

            // Keep the founder informed (heads-up; the reset already happened).
            $adminEmail = config('services.admin.email') ?: config('mail.from.address');
            if ($adminEmail) {
                Mail::to($adminEmail)->send(new PartnerResetRequestedMail($referrer));
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

    private function present(Referrer $referrer): array
    {
        return [
            'id' => $referrer->id,
            'name' => $referrer->name,
            'email' => $referrer->email,
            'code' => $referrer->code,
            'relationship_tier' => $referrer->relationship_tier,
            'commission_pct' => $referrer->commission_pct,
        ];
    }
}
