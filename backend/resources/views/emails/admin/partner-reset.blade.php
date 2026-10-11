{{-- Email 26 — owner heads-up: a partner reset their passcode. Copy kept from the original template. --}}
<x-email::layouts.internal title="Partner passcode reset" :preheader="$partnerName.' reset their partner passcode.'">
<x-email::blocks.heading-intro-internal eyebrow="Partner Portal" heading="Partner passcode reset">
{{ $partnerName }} used "forgot passcode" on the partner portal, so a new passcode has been automatically issued to their email.
</x-email::blocks.heading-intro-internal>
<x-email::blocks.spacer :height="20" />
<x-email::blocks.summary-table :rows="$rows" />
<x-email::blocks.paragraph>No action needed — this is just a heads-up. If it looks suspicious, you can issue another passcode from the Referral Partners screen.</x-email::blocks.paragraph>
</x-email::layouts.internal>
