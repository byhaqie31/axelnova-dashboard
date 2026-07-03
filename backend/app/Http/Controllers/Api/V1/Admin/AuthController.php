<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // Only the cockpit tier (founder/partner) signs in here; workspace roles
        // authenticate against /team (Phase 3b), not the admin SPA.
        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->isCockpit()) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $token = $user->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tier' => $user->tier(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'tier' => $user->tier(),
        ]);
    }

    /**
     * Admin → Team direct sign-in. Exchanges the current cockpit session for a
     * fresh workspace token so the portal jump never re-asks for credentials.
     * Cockpit-only via the route group, and the ONLY sanctioned bridge between
     * the two surfaces — the tokens themselves stay non-replayable (the
     * `abilities:` middleware rejects a cockpit token on /v1/team/* and vice
     * versa). Every exchange is auto-audited as `team-session`.
     */
    public function teamSession(Request $request): JsonResponse
    {
        $user = $request->user();

        $token = $user->createToken('team-spa', ['workspace'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tier' => $user->tier(),
            ],
        ]);
    }
}
