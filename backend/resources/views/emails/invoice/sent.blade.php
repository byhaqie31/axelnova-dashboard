{{-- Email 3 — invoice (kit: 03-invoice.html). --}}
@php
    $preheader = "Invoice {$invoiceNumber} for {$amountDue}".(filled($dueDate) ? ", due {$dueDate}." : '.');
    $intro = filled($projectName)
        ? "Hi {$name}, here's your invoice for the {$projectName} project."
        : "Hi {$name}, here's your invoice from Axel Nova Ventures.";
    $pdfLine = $attached ? 'The PDF is attached to this email and linked below.' : 'The PDF is linked below.';
@endphp
<x-email::layouts.client :title="'Invoice '.$invoiceNumber" :preheader="$preheader">
<x-email::blocks.heading-intro eyebrow="Invoice" :heading="'Invoice '.$invoiceNumber">
{{ $intro }} {{ $pdfLine }}
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.highlight-panel label="Amount due" :amount="$amountDue" :note="filled($dueDate) ? 'Due '.$dueDate : null" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.summary-table :rows="[
    ['Invoice no', $invoiceNumber],
    ['Quote ref', $quoteRef],
    ['Issued', $issuedDate],
    ['Due', $dueDate],
]" />
<x-email::blocks.section-label text="How to pay" />
<x-email::blocks.summary-table :rows="[
    ['Bank', $bank['name']],
    ['Account name', $bank['holder']],
    ['Account no', $bank['account']],
    ['Reference', $invoiceNumber],
    ['Card or FPX', $onlinePaymentLink],
]" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$pdfUrl" label="View PDF" />
<x-email::blocks.paragraph>Once your payment is recorded, I'll send you a receipt.</x-email::blocks.paragraph>
<x-email::blocks.signature />
</x-email::layouts.client>
