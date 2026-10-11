{{-- Email 7 — refund confirmation (new; mailable + trigger in Phase 3). --}}
<x-email::layouts.client title="Refund processed" :preheader="$amount.' refunded for order '.$orderNumber.'.'">
<x-email::blocks.heading-intro eyebrow="Refund processed" heading="Your refund is on its way">
Hi {{ $name }}, I've processed a refund for order {{ $orderNumber }}. Here are the details for your records.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.highlight-panel label="Refunded" :amount="$amount" :note="'Refunded '.$refundDate.' via '.$methodLabel" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.summary-table :rows="[
    ['Order', $orderNumber],
    ['Original payment', $originalPayment],
    ['Refund ref', $refundReference],
    ['Method', $methodLabel],
]" />
<x-email::blocks.paragraph>Depending on your bank, the refund can take a few working days to show in your account. If anything looks off, just reply to this email.</x-email::blocks.paragraph>
<x-email::blocks.signature />
</x-email::layouts.client>
