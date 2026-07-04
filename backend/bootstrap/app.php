<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsurePartnerType;
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
        // Trust the local nginx reverse proxy so Laravel reads the real client IP
        // and scheme from X-Forwarded-* headers (correct per-IP rate limiting,
        // HTTPS-aware URL generation behind TLS-terminating nginx).
        $middleware->trustProxies(at: '*');

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
