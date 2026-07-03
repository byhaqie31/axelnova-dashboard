<?php

use App\Models\Referrer;
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
    | leaked Referrer token can never satisfy it — Sanctum's Guard rejects a
    | tokenable whose model doesn't match the guard's provider). The `/partners`
    | portal uses the isolated `referral` guard on the `referral_partners` table.
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

        // Isolated affiliate guard — /v1/partner/* only. Tokens minted for a
        // Referrer authenticate here and nowhere else.
        'referral' => [
            'driver' => 'sanctum',
            'provider' => 'referrers',
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

        'referrers' => [
            'driver' => 'eloquent',
            'model' => Referrer::class,
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
