<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TeamWelcomeMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Team provisioning. Every action is founder-only via the `manage-users` gate.
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

    /**
     * Full profile for the founder's user detail page — the lean roster fields
     * plus the teammate's self-filled contact / bank / address (kept off the
     * list payload; only surfaced on the single-record view) and completeness.
     */
    public function show(User $user): JsonResponse
    {
        Gate::authorize('manage-users');

        return response()->json($this->present($user) + [
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
        ]);
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
            'monthly_allowance_myr' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $user = User::create($data);

        // Welcome the teammate with their sign-in details ($data['password'] is
        // still the plaintext — the model only ever stores its hash). Queued,
        // and never allowed to fail provisioning: the founder also sees the
        // one-time credentials on-screen as a fallback if delivery hiccups.
        try {
            Mail::to($user->email, $user->name)->send(new TeamWelcomeMail($user, $data['password']));
        } catch (\Throwable $e) {
            Log::warning('Welcome email could not be queued for new teammate.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json($this->present($user), 201);
    }

    /** Rename, change role, or adjust the monthly allowance. */
    public function update(Request $request, User $user): JsonResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:150'],
            'role' => ['sometimes', Rule::in(User::WORKSPACE_ROLES)],
            'monthly_allowance_myr' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        // Never leave the platform without a founder — the only role that can
        // manage users. Blocks demoting the last one.
        $demotesLastFounder = $user->isFounder()
            && ($data['role'] ?? 'founder') !== 'founder'
            && User::where('role', 'founder')->count() <= 1;

        if ($demotesLastFounder) {
            return response()->json(['message' => 'Cannot demote the last founder.'], 422);
        }

        $roleChanged = array_key_exists('role', $data) && $data['role'] !== $user->role;

        $user->update($data);

        // A role change must not survive on an already-issued token/session.
        // Renaming or re-budgeting the allowance doesn't warrant signing the
        // teammate out — only an actual role change does.
        if ($roleChanged) {
            $user->tokens()->delete();
        }

        return response()->json($this->present($user->fresh()));
    }

    /**
     * Lock a teammate out: stamps `deactivated_at` (checked at login on both
     * /v1/admin and /v1/team) and revokes every outstanding token so an
     * already-open session dies immediately, not just on next login attempt.
     *
     * No separate "last founder" guard is needed here (unlike `update()`'s role
     * demote, which has none): `manage-users` requires the actor to BE an
     * active founder, and the self-deactivation block above is unconditional.
     * So the acting founder can never deactivate themselves, and deactivating
     * a *different* founder always leaves the actor active — the platform can
     * never end up with zero active founders through this endpoint.
     */
    public function deactivate(Request $request, User $user): JsonResponse
    {
        Gate::authorize('manage-users');

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        if ($user->isDeactivated()) {
            return response()->json(['message' => 'This teammate is already deactivated.'], 422);
        }

        // `deactivated_at` is deliberately NOT in User::$fillable (it must never
        // be settable through the generic name/role/allowance update() above) —
        // set it directly rather than via mass-assignment.
        $user->forceFill(['deactivated_at' => now()])->save();
        $user->tokens()->delete();

        return response()->json($this->present($user->fresh()));
    }

    /** Undo a deactivation — clears the lockout, login works again (a fresh sign-in mints the token). */
    public function reactivate(Request $request, User $user): JsonResponse
    {
        Gate::authorize('manage-users');

        if (! $user->isDeactivated()) {
            return response()->json(['message' => 'This teammate is already active.'], 422);
        }

        $user->forceFill(['deactivated_at' => null])->save();

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
            'monthly_allowance_myr' => $user->monthly_allowance_myr,
            'deactivated_at' => $user->deactivated_at,
            'created_at' => $user->created_at,
        ];
    }
}
