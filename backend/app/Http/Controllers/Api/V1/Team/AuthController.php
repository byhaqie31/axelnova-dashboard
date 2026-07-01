<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
