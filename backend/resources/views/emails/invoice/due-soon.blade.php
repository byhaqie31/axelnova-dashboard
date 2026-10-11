{{-- Email 12 — invoice due soon (new; scheduled in Phase 5). --}}
<x-email::layouts.client :title="'Invoice '.$invoiceNumber.' is due soon'" :preheader="'Invoice '.$invoiceNumber.' for '.$amountDue.' is due '.$dueDate.'.'">
<x-email::blocks.heading-intro eyebrow="Payment reminder" :heading="'Invoice '.$invoiceNumber.' is due soon'">
Hi {{ $name }}, a friendly reminder that this invoice is due on {{ $dueDate }}. If you've already paid, thank you — please ignore this email.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.highlight-panel label="Amount due" :amount="$amountDue" :note="'Due '.$dueDate" />
<x-email::blocks.section-label text="How to pay" />
<x-email::blocks.summary-table :rows="[
    ['Bank', $bank['name']],
    ['Account name', $bank['holder']],
    ['Account no', $bank['account']],
    ['Reference', $invoiceNumber],
]" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$pdfUrl" label="View invoice" />
<x-email::blocks.signature />
</x-email::layouts.client>
