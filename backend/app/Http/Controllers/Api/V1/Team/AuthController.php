<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Controllers\Controller;
use App\Mail\TeamPasswordResetRequestedMail;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Workspace auth (the /team surface). Mirrors the admin AuthController but admits
 * the full workspace tier — founder + marketer + engineer — since every
 * internal role works here. Tokens are scoped ['workspace']; the cockpit-only
 * actions live behind /v1/admin (role:cockpit) and are unreachable from here.
 * Also owns self-service profile updates (`updateMe`) — a natural extension of
 * `me()`, which already presents "my own" session/profile data.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // Any internal role signs in here (all four are workspace-eligible). Bad
        // credentials, a role outside the enum, or a deactivated account
        // (Task 8 — /admin/users) are all rejected uniformly.
        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->isWorkspace() || $user->isDeactivated()) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $token = $user->createToken('team-spa', ['workspace'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->present($user),
        ]);
    }

    /**
     * "Forgot password" for the workspace. Team passwords have no self-service
     * reset — only the founder resets them (Users screen) — so this simply
     * notifies the founder by email that the member is locked out. The response
     * is identical whether or not the email matches an account, so it never
     * reveals who works here. Abuse is bounded by the shared login throttle.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user && $user->isWorkspace()) {
            ActivityLog::create([
                'actor_id' => null,
                'action' => 'team.password_reset_requested',
                'subject_type' => class_basename($user),
                'subject_id' => $user->id,
                'changes' => null,
            ]);

            $adminEmail = config('services.admin.email') ?: config('mail.from.address');
            if ($adminEmail) {
                Mail::to($adminEmail)->send(new TeamPasswordResetRequestedMail($user));
            }
        }

        return response()->json([
            'message' => 'If that email matches a team account, the admin has been notified and will reset your password.',
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
     * Self-service profile update — the Profile page's display name +
     * availability status. Self-only by construction: there's no user-id
     * route param, it always acts on `$request->user()`.
     */
    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:150'],
            'availability' => ['sometimes', Rule::in(['available', 'busy'])],
            // Self-filled profile — teammates own their contact / bank / address.
            // All nullable so clearing a field back to empty is allowed.
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bank_account_number' => ['sometimes', 'nullable', 'string', 'max:60'],
            'bank_account_holder' => ['sometimes', 'nullable', 'string', 'max:150'],
            'address_line1' => ['sometimes', 'nullable', 'string', 'max:200'],
            'address_line2' => ['sometimes', 'nullable', 'string', 'max:200'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postcode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $user->update($data);

        return response()->json($this->present($user->fresh()));
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'tier' => $user->tier(),
            'availability' => $user->availability,
            // Self-filled profile (owner-only view — the teammate's own record).
            'phone' => $user->phone,
            'bank_name' => $user->bank_name,
            'bank_account_number' => $user->bank_account_number,
            'bank_account_holder' => $user->bank_account_holder,
            'address_line1' => $user->address_line1,
            'address_line2' => $user->address_line2,
            'city' => $user->city,
            'postcode' => $user->postcode,
            'state' => $user->state,
            'country' => $user->country,
            'profile_complete' => $user->profileComplete(),
            'profile_missing' => $user->profileMissing(),
        ];
    }
}
