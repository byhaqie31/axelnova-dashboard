{{-- `php artisan mail:test` — not a catalog email. --}}
<x-email::layouts.internal title="Test email" preheader="If you can read this, sending works.">
<x-email::blocks.heading-intro-internal eyebrow="Test" heading="Test email">
If you can read this, sending from the Axel Nova dashboard works.
</x-email::blocks.heading-intro-internal>
<x-email::blocks.spacer :height="20" />
<x-email::blocks.summary-table :rows="[
    ['Mailer', $mailerName],
    ['Sent', $sentAt],
]" />
</x-email::layouts.internal>
