<?php

return [
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'admin' => [
        'email' => env('ADMIN_NOTIFICATION_EMAIL', 'baihaqie@axelnova.tech'),
        'name' => env('ADMIN_NAME', 'Ahmad Baihaqie'),
        'whatsapp_url' => env('ADMIN_WHATSAPP_URL', 'https://wa.me/60183173103'),
    ],

    // Public site / Nuxt app — used to build links in emails + the referral link.
    'frontend' => [
        // The app origin — also the admin cockpit's origin, and the CORS anchor.
        'url' => env('FRONTEND_URL', 'http://localhost:3003'),
        // The PUBLIC marketing site where clients / partners / teammates land.
        // Distinct from `url` when the admin cockpit runs on its own subdomain
        // (e.g. admin.example.com vs example.com). Falls back to the app URL so
        // single-domain and dev setups need no extra config.
        'public_url' => env('PUBLIC_SITE_URL', env('FRONTEND_URL', 'http://localhost:3003')),
    ],

    // Nuxt page-cache purge (frontend/server/routes/_cache/purge.post.ts) —
    // App\Support\SiteCache calls it when a published blog post changes. Both
    // unset → no-op (dev, CI). Prod: container-to-container, e.g.
    // http://axelnova-frontend:3000/_cache/purge; token = NUXT_CACHE_PURGE_TOKEN.
    'site_cache' => [
        'purge_url' => env('SITE_CACHE_PURGE_URL'),
        'purge_token' => env('SITE_CACHE_PURGE_TOKEN'),
    ],

    // Studio branding for generated documents (quotation PDF).
    'studio' => [
        'logo_url' => env('STUDIO_LOGO_URL'), // URL or base64 data URI; blank → text wordmark
    ],
];
