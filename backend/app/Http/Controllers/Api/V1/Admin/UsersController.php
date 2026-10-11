<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TeamPasswordResetMail;
use App\Mail\TeamWelcomeMail;
use App\Models\AccessToken;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Auth\AccessLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
    public function store(Request $request, AccessLinks $links): JsonResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:200', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(User::WORKSPACE_ROLES)],
            'monthly_allowance_myr' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        // No password is chosen for them (Phase 2): the account starts with an
        // unusable random one, and the welcome email carries a one-time link
        // where the teammate sets their own. password_set_at = null marks
        // "set-up pending" for the Users screen.
        $user = User::create($data + ['password' => Str::random(64)]);
        $user->forceFill(['password_set_at' => null])->save();

        $this->sendLink($user, $links);

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
    public function deactivate(Request $request, User $user, AccessLinks $links): JsonResponse
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
        // Outstanding set-up/reset links die too, so reactivating can't revive them.
        $links->revokeAll($user);

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

    /**
     * Email the teammate a one-time link (Phase 2): a fresh set-up link if they
     * never finished set-up, otherwise a 60-minute reset link. Nothing changes
     * until they use it — to lock someone out, deactivate the account.
     */
    public function resetPassword(Request $request, User $user, AccessLinks $links): JsonResponse
    {
        Gate::authorize('manage-users');

        if ($user->isDeactivated()) {
            return response()->json(['message' => 'Reactivate this teammate before resetting their password.'], 422);
        }

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'team.password_reset',
            'subject_type' => class_basename($user),
            'subject_id' => $user->id,
            'changes' => null,
        ]);

        $setup = $this->sendLink($user, $links);

        return response()->json([
            'message' => $setup
                ? "A new set-up link has been emailed to {$user->name}."
                : "A password reset link has been emailed to {$user->name}.",
        ]);
    }

    /**
     * Set-up link while set-up is pending, reset link otherwise. Queued, and
     * never allowed to fail the request — the founder can resend. Returns
     * whether it was a set-up link.
     */
    private function sendLink(User $user, AccessLinks $links): bool
    {
        $setup = $user->password_set_at === null;

        try {
            $url = $links->issue($user, $setup ? AccessToken::SETUP : AccessToken::RESET);
            Mail::to($user->email, $user->name)->send(
                $setup ? new TeamWelcomeMail($user, $url) : new TeamPasswordResetMail($user, $url),
            );
        } catch (\Throwable $e) {
            Log::warning('Team access link could not be sent.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return $setup;
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
            // Invited but hasn't chosen a password yet → "Resend set-up link".
            'setup_pending' => $user->password_set_at === null,
            'created_at' => $user->created_at,
        ];
    }
}
