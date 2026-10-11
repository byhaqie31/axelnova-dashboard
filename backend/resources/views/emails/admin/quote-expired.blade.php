{{-- Email 31 — owner alert: a quote expired without a reply (new; scheduled in Phase 5). --}}
<x-email::layouts.internal title="Quote expired" :preheader="$reference.' for '.$clientName.' expired without a reply.'">
<x-email::blocks.heading-intro-internal eyebrow="Quotations" heading="A quote expired without a reply">
{{ $clientName }} didn't respond to {{ $reference }} before it expired. A short follow-up might still win it.
</x-email::blocks.heading-intro-internal>
<x-email::blocks.spacer :height="20" />
<x-email::blocks.summary-table :rows="[
    ['Quote', $reference],
    ['Client', $clientName],
    ['Value', $total],
    ['Expired on', $expiredDate],
]" />
<x-email::blocks.spacer :height="24" />
<x-email::blocks.main-button :url="$adminUrl" label="Open quote" />
</x-email::layouts.internal>
