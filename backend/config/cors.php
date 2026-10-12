<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| Allowlist, never `*`. In production the browser calls the API on the admin
| host (NUXT_PUBLIC_API_BASE = https://admin.axelnovaventures.com), so the
| admin panel is same-origin while every public page on axelnovaventures.com
| (quote, contact, referral, feedback forms, page-view beacon) is cross-origin
| and needs the apex entries below. FRONTEND_URL is the admin origin; dev
| (localhost:3003 → localhost:8003) is cross-origin too.
|
*/

$origins = [
    env('FRONTEND_URL'),
    'https://axelnovaventures.com',
    'https://www.axelnovaventures.com',
];

if (env('APP_ENV') !== 'production') {
    $origins[] = 'http://localhost:3003';
    $origins[] = 'http://127.0.0.1:3003';
}

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_filter($origins))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Auth is a bearer header, not cookies — keep responses non-credentialed.
    'supports_credentials' => false,
];
