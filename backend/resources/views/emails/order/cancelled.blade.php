{{-- Email 11 — order cancelled (new; mailable + trigger in Phase 3). --}}
<x-email::layouts.client title="Order cancelled" :preheader="'Order '.$orderNumber.' has been cancelled.'">
<x-email::blocks.heading-intro eyebrow="Order cancelled" heading="Your order has been cancelled">
Hi {{ $name }}, this confirms that order {{ $orderNumber }} has been cancelled.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.summary-table :rows="[
    ['Order', $orderNumber],
    ['Package', $packageName],
    ['Cancelled on', $cancelledDate],
    ['Refund', $refundNote],
]" />
<x-email::blocks.paragraph>If this wasn't expected, or you'd like to pick things up again later, just reply to this email.</x-email::blocks.paragraph>
<x-email::blocks.signature />
</x-email::layouts.client>
