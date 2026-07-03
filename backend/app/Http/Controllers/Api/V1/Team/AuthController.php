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
use Illuminate\Validation\ValidationException;

/**
 * Workspace auth (the /team surface). Mirrors the admin AuthController but admits
 * the full workspace tier — founder + partner + marketer + engineer — since every
 * internal role works here. Tokens are scoped ['workspace']; the cockpit-only
 * actions live behind /v1/admin (role:cockpit) and are unreachable from here.
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
        // credentials or a role outside the enum are rejected uniformly.
        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->isWorkspace()) {
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

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'tier' => $user->tier(),
        ];
    }
}
