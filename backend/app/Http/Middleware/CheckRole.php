<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Gate a route by role or tier. Each parameter is either a bare role
     * ('founder', 'marketer', …) or a tier keyword that expands to its member
     * roles — 'cockpit' → founder/partner, 'workspace' → all four. Passing
     * several parameters (`role:founder,marketer`) allows any of them.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $allowed = collect($roles)->flatMap(fn (string $role) => match ($role) {
            'cockpit' => User::COCKPIT_ROLES,
            'workspace' => User::WORKSPACE_ROLES,
            default => [$role],
        })->unique();

        return $allowed->contains($user->role)
            ? $next($request)
            : response()->json(['message' => 'Forbidden.'], 403);
    }
}
