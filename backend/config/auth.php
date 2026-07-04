<?php

use App\Models\ExternalAccount;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Three surfaces, two backends. `/admin` + `/team` authenticate against the
    | `users` table via the `sanctum` guard (scoped to the `users` provider, so a
    | leaked partner token can never satisfy it — Sanctum's Guard rejects a
    | tokenable whose model doesn't match the guard's provider). The `/partners`
    | portal uses the isolated `external` guard on the `external_accounts` table
    | (the unified referrer + investor identity, Task 9).
    |
    | Publishing this file makes the previously-implicit `sanctum` guard explicit:
    | Sanctum injected it with `provider => null` (any tokenable), which we now pin
    | to `users` for the isolation guarantee.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],

        // Isolated partner guard — /v1/partner/* only. Tokens minted for an
        // ExternalAccount (referrer OR investor) authenticate here and nowhere else.
        'external' => [
            'driver' => 'sanctum',
            'provider' => 'external_accounts',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'external_accounts' => [
            'driver' => 'eloquent',
            'model' => ExternalAccount::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | Partners have NO password-reset broker by design — there is no self-service
    | reset. Staff regenerate a passcode via the team reset-passcode action.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
