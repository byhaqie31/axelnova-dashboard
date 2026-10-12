<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline hardening headers on every response. The API serves JSON only, so
 * DENY framing outright and keep it out of search (X-Robots-Tag) on every host —
 * nginx serves /api on the public and the admin host alike. The Nuxt app sets
 * its own headers via nitro routeRules + server/middleware/admin-host.ts.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
