{{-- Email 6 — payment receipt (kit: 06-payment-receipt.html). Mailable arrives in Phase 3. --}}
@php
    $preheader = "{$amountPaid} received".(filled($invoiceNumber) ? " against invoice {$invoiceNumber}." : '.');
    $receiptLine = $attached ? 'Your receipt is attached to this email and linked below.' : 'Your receipt is linked below.';
@endphp
<x-email::layouts.client title="Payment received" :preheader="$preheader">
<x-email::blocks.heading-intro eyebrow="Payment received" heading="Thanks, payment received">
Hi {{ $name }}, I've recorded your payment. {{ $receiptLine }}
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.highlight-panel label="Amount paid" :amount="$amountPaid" :note="'Paid '.$paidDate.' by '.$methodLabel" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.summary-table :rows="[
    ['Receipt no', $receiptNumber],
    ['Payment ref', $paymentReference],
    ['Method', $methodLabel],
    ['Paid against', filled($invoiceNumber) ? 'Invoice '.$invoiceNumber : null],
    ['Balance left', $balanceLeft],
]" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$receiptUrl" label="View receipt" />
<x-email::blocks.signature />
</x-email::layouts.client>
