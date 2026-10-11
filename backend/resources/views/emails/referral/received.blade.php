{{-- Email 15 — thanks for the referral. Copy kept from the original template. --}}
@php
    $heading = "Thanks for the referral, {$name}";
@endphp
<x-email::layouts.client title="Thanks for the referral" :preheader="'I\'ve received your referral of '.$businessName.'.'">
<x-email::blocks.heading-intro eyebrow="Referral received" :heading="$heading">
I've received your referral of {{ $businessName }} and made a note of it. I'll reach out to them (usually within 3 business days) and keep you posted on how it goes.
</x-email::blocks.heading-intro>
<x-email::blocks.paragraph>If it becomes a signed, paid project, your commission follows per the Partner Program terms (your current tier is {{ $tierPct }}% of the final project value, capped at {{ $cap }} per referral).</x-email::blocks.paragraph>
<x-email::blocks.paragraph>Thanks for thinking of me. It genuinely means a lot.</x-email::blocks.paragraph>
<x-email::blocks.signature signoff="Best," />
</x-email::layouts.client>
