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
        'whatsapp_url' => env('ADMIN_WHATSAPP_URL', 'https://wa.me/60177109486'),
    ],

    // Public site / Nuxt app — used to build links (e.g. the quotation PDF) in emails.
    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:3003'),
    ],

    // Studio branding for generated documents (quotation PDF).
    'studio' => [
        'logo_url' => env('STUDIO_LOGO_URL'), // URL or base64 data URI; blank → text wordmark
    ],
];
