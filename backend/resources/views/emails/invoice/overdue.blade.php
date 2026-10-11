{{-- Email 13 — invoice overdue (new; scheduled in Phase 5). --}}
@php
    $overdueTitle = $daysOverdue === 1 ? '1 day past the due date' : "{$daysOverdue} days past the due date";
@endphp
<x-email::layouts.client :title="'Invoice '.$invoiceNumber.' is overdue'" :preheader="'Invoice '.$invoiceNumber.' for '.$amountDue.' was due '.$dueDate.'.'">
<x-email::blocks.heading-intro eyebrow="Payment overdue" :heading="'Invoice '.$invoiceNumber.' is overdue'">
Hi {{ $name }}, this invoice was due on {{ $dueDate }} and I haven't received payment yet. If you've already paid, thank you — just reply with the reference and I'll match it up.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.warning-box label="Overdue" :title="$overdueTitle" text="Please arrange payment at your earliest convenience." />
<x-email::blocks.spacer :height="24" />
<x-email::blocks.highlight-panel label="Amount due" :amount="$amountDue" :note="'Was due '.$dueDate" />
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
