<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| Allowlist, never `*`. Production is same-origin (nginx routes /api/* and
| the Nuxt app under one domain) so CORS is mostly inert there; this guards
| dev (localhost:3003 → localhost:8003 is cross-origin) and any future
| split-origin topology. FRONTEND_URL carries the canonical origin per
| environment; the apex domains are a safety net in case the VPS env drifts.
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
