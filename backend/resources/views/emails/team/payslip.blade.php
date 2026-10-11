{{-- Email 23 — payslip on payroll settle (new; mailable + trigger in Phase 6). --}}
<x-email::layouts.client :title="'Your payslip for '.$period" :preheader="'Your pay for '.$period.' has been settled.'">
<x-email::blocks.heading-intro eyebrow="Payslip" :heading="'Your payslip for '.$period">
Hi {{ $name }}, your pay for {{ $period }} has been settled. Here's the breakdown.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.highlight-panel label="Total paid" :amount="$gross" :note="'Paid '.$paidDate" />
@if ($lines !== [])
<x-email::blocks.section-label text="Breakdown" />
<x-email::blocks.line-items :items="$lines" :total="['label' => 'Total', 'amount' => $gross]" />
@endif
<x-email::blocks.spacer :height="32" />
<x-email::blocks.summary-table :rows="[
    ['Paid on', $paidDate],
    ['Method', $methodLabel],
]" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$payslipUrl" label="View payslip" />
<x-email::blocks.signature signoff="Thanks for all your work," />
</x-email::layouts.client>
