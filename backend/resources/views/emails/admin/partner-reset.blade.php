{{-- Email 26 — owner heads-up: a partner set a new passcode through a reset link (Phase 2). --}}
<x-email::layouts.internal title="Partner passcode changed" :preheader="$partnerName.' set a new partner passcode.'">
<x-email::blocks.heading-intro-internal eyebrow="Partner Portal" heading="Partner passcode changed">
{{ $partnerName }} set a new passcode through a reset link.
</x-email::blocks.heading-intro-internal>
<x-email::blocks.spacer :height="20" />
<x-email::blocks.summary-table :rows="$rows" />
<x-email::blocks.paragraph>No action needed — this is just a heads-up. If it looks suspicious, check the partner on the Referral Partners screen.</x-email::blocks.paragraph>
</x-email::layouts.internal>
