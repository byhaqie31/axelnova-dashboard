<?php

/*
 * Email look-and-feel — owner design kit: docs/global/email-kit/.
 * The ONE place for the accent colour and the logo; every other style in the
 * email templates is fixed kit markup (resources/views/emails/).
 */
return [
    'accent' => '#4B45D6',

    // PNG only — Gmail drops SVG. An absolute URL, because mail clients fetch
    // it: the transparent "A" mark from the PDF letterhead, served by the
    // website from frontend/public/email/.
    'logo' => [
        'url' => env(
            'EMAIL_LOGO_URL',
            rtrim((string) env('PUBLIC_SITE_URL', env('FRONTEND_URL', 'http://localhost:3003')), '/').'/email/axn-mark.png',
        ),
        'width' => 16,
        'height' => 24,
    ],

    // The founder's sign-off on client-facing emails.
    'signature' => [
        'name' => 'Ahmad Baihaqie',
        'title' => 'Founder, Axel Nova Ventures',
        'email' => 'baihaqie@axelnova.tech',
    ],

    // Client-layout footer.
    'company' => [
        'name' => 'Axel Nova Ventures',
        'registration' => '202603119899 (CA0420977-U)',
        'location' => 'Kuala Lumpur, Malaysia',
    ],
];
