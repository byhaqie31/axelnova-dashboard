<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Team provisioning. Every action is founder-only via the `manage-users` gate;
 * a partner reaches these routes (cockpit tier) but is stopped by the gate (403).
 */
class UsersController extends Controller
{
    /** Team roster for the founder's user-management screen. */
    public function index(): JsonResponse
    {
        Gate::authorize('manage-users');

        return response()->json(
            User::query()->orderBy('name')->get()->map(fn (User $user) => $this->present($user)),
        );
    }

    /** Provision a teammate. Password is hashed by the model's `hashed` cast. */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:200', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12'],
            'role' => ['required', Rule::in(User::WORKSPACE_ROLES)],
        ]);

        return response()->json($this->present(User::create($data)), 201);
    }

    /** Rename or change a teammate's role. */
    public function update(Request $request, User $user): JsonResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:150'],
            'role' => ['sometimes', Rule::in(User::WORKSPACE_ROLES)],
        ]);

        // Never leave the platform without a founder — the only role that can
        // manage users. Blocks demoting the last one.
        $demotesLastFounder = $user->isFounder()
            && ($data['role'] ?? 'founder') !== 'founder'
            && User::where('role', 'founder')->count() <= 1;

        if ($demotesLastFounder) {
            return response()->json(['message' => 'Cannot demote the last founder.'], 422);
        }

        $user->update($data);

        // A role change must not survive on an already-issued token/session.
        $user->tokens()->delete();

        return response()->json($this->present($user->fresh()));
    }

    /**
     * Sign a teammate out everywhere by revoking their tokens. (Not a permanent
     * lock — that would need an is_active flag checked at login, out of Phase 0
     * scope.)
     */
    public function deactivate(Request $request, User $user): JsonResponse
    {
        Gate::authorize('manage-users');

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        $user->tokens()->delete();

        return response()->json(['ok' => true]);
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'tier' => $user->tier(),
            'created_at' => $user->created_at,
        ];
    }
}
