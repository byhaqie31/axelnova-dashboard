{{-- Email 9 — project delivered (kit: 09-project-delivered.html). Mailable arrives in Phase 3. --}}
@php
    $heading = "Your project is delivered, {$name}";
    $reviewLine = filled($reviewWindow)
        ? "You have {$reviewWindow} to check everything. Reply to this email with any changes and I'll take care of them."
        : "Reply to this email with any changes and I'll take care of them.";
@endphp
<x-email::layouts.client title="Your project is delivered" preheader="Everything is handed over and ready for you to review.">
<x-email::blocks.heading-intro eyebrow="Project delivered" :heading="$heading">
Everything is handed over and ready for you to review.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.order-tracker :current="3" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.summary-table :rows="[
    ['Order', $orderNumber],
    ['Package', $packageName],
]" />
@if ($handoverItems !== [])
<x-email::blocks.section-label text="What was handed over" />
<x-email::blocks.line-items :items="array_map(fn (string $item) => ['label' => $item], $handoverItems)" />
@endif
<x-email::blocks.paragraph>{{ $reviewLine }}</x-email::blocks.paragraph>
@if ($balance)
<x-email::blocks.spacer :height="32" />
<x-email::blocks.highlight-panel label="Balance due" :amount="$balance['amount']" :note="'Invoice '.$balance['invoiceNumber'].(filled($balance['dueDate']) ? ', due '.$balance['dueDate'] : '')" />
@if (filled($invoiceUrl))
<x-email::blocks.spacer :height="24" />
<x-email::blocks.main-button :url="$invoiceUrl" label="View invoice" />
@endif
@endif
<x-email::blocks.signature />
</x-email::layouts.client>
