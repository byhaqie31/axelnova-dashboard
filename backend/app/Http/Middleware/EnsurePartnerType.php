<?php

namespace App\Http\Middleware;

use App\Models\ExternalAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a partner-portal route to one account type. Referrer-only endpoints
 * (dashboard, submit-referral) 403 an investor token, and vice-versa. Runs after
 * `auth:external`, so $request->user() is the authenticated ExternalAccount.
 *
 * Usage: ->middleware('partner.type:referrer')
 */
class EnsurePartnerType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $account = $request->user();

        if (! $account instanceof ExternalAccount || $account->type !== $type) {
            return response()->json([
                'message' => 'This area is not available for your account type.',
            ], 403);
        }

        return $next($request);
    }
}
