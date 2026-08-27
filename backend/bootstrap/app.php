<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsurePartnerType;
use App\Http\Middleware\ResolveCloudflareClientIp;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust ONLY the proxy hops that actually front this container, so
        // Laravel reads the real client IP and scheme from X-Forwarded-*
        // (correct per-IP rate limiting, HTTPS-aware URL generation).
        //
        // The production chain is:
        //   visitor → Cloudflare edge → cloudflared (loopback) → host nginx → container
        // Host nginx rewrites $remote_addr from CF-Connecting-IP and appends it
        // to X-Forwarded-For, so the RIGHTMOST forwarded entry is the true client.
        //
        // This was `at: '*'`, which makes Symfony treat every hop as trusted and
        // return the LEFTMOST X-Forwarded-For entry instead. Cloudflare APPENDS
        // to any attacker-supplied X-Forwarded-For, so that entry was fully
        // attacker-controlled — every per-IP throttle in the app (login 10/min,
        // quotes 8/hour, inquiries, feedback) could be bypassed by rotating one
        // header value. Trusting only the private hops makes Symfony walk the
        // chain from the right and stop at the first untrusted entry: the real IP.
        //
        // Safe to trust the private ranges: the container publishes on
        // 127.0.0.1:8003 (docker-compose.prod.yml), so nothing off-host can
        // reach it directly to forge a hop.
        // Must run before TrustProxies: collapses the forwarded chain to
        // Cloudflare's un-forgeable CF-Connecting-IP when one is present, so the
        // real client IP does not depend on the host nginx vhost appending
        // correctly (that file lives on the VPS, not in this repo).
        $middleware->prepend(ResolveCloudflareClientIp::class);

        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
            '10.0.0.0/8',      // docker / private
            '172.16.0.0/12',   // docker bridge networks (axelnova-shared is 172.20/16)
            '192.168.0.0/16',
            'fc00::/7',        // IPv6 unique-local
        ]);

        // Global rate-limit floor on every /api route (see the 'api' limiter in
        // AppServiceProvider). Throttling used to be opt-in per route group,
        // which left the public token lookups (/v1/documents/{token},
        // /v1/feedback/{token}) and the authenticated writes on /v1/partner,
        // /v1/team and /v1/admin with no ceiling at all. The tighter per-route
        // throttles still apply on top of this.
        $middleware->throttleApi();

        // Baseline security headers on every response (API + health check).
        $middleware->append(SecurityHeaders::class);

        // API-only app: never redirect guests to a web login page. Without this,
        // the framework's default callback resolves route('login') — which does
        // not exist — and an unauthenticated hit 500s instead of 401ing.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'role' => CheckRole::class,
            // Partner-portal type gate — referrer-only / investor-only endpoints
            // (runs after auth:external). e.g. 'partner.type:referrer'.
            'partner.type' => EnsurePartnerType::class,
            // Sanctum token-ability gate. `role:` checks WHO the user is;
            // `abilities:` checks WHICH surface the token was minted for
            // (cockpit / workspace / partner) — without it, a cockpit user's
            // *team* token would replay fine against /v1/admin/*.
            'abilities' => CheckAbilities::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // This is an API-only app — always render api/* errors as JSON. Without
        // this, a request lacking an Accept header turns a 401 into a redirect
        // to the (nonexistent) `login` route and surfaces as a 500.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    })->create();
