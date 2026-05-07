<?php

return [
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'turnstile' => [
        'secret' => env('TURNSTILE_SECRET'),
        'site_key' => env('TURNSTILE_SITE_KEY'),
    ],

    'admin' => [
        'email' => env('ADMIN_NOTIFICATION_EMAIL', 'baihaqie@axelnova.tech'),
        'name' => env('ADMIN_NAME', 'Ahmad Baihaqie'),
    ],
];
