{{-- Email 29 — owner alert: a new referral came in (new; mailable + trigger in Phase 4). --}}
<x-email::layouts.internal title="New referral" :preheader="$referrerName.' referred '.$businessName.'.'">
<x-email::blocks.heading-intro-internal eyebrow="Partner Program" heading="New referral">
{{ $referrerName }} referred {{ $businessName }}. The thank-you email promised them you'd reach out within 3 business days.
</x-email::blocks.heading-intro-internal>
<x-email::blocks.spacer :height="20" />
<x-email::blocks.summary-table :rows="$rows" />
<x-email::blocks.spacer :height="24" />
<x-email::blocks.main-button :url="$adminUrl" label="Open referral" />
</x-email::layouts.internal>
