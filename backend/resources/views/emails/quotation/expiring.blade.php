{{-- Email 14 — quote expiring soon (new; scheduled in Phase 5). --}}
<x-email::layouts.client title="Your quote expires soon" :preheader="'Quotation '.$reference.' is valid until '.$validUntil.'.'">
<x-email::blocks.heading-intro eyebrow="Quote expiring" :heading="'Your quote expires on '.$validUntil">
Hi {{ $name }}, just a heads-up that quotation {{ $reference }} is valid until {{ $validUntil }}. If you'd like to go ahead, or need anything changed first, let me know.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.highlight-panel label="Total" :amount="$total" :note="'Valid until '.$validUntil" />
@if (filled($pdfUrl))
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$pdfUrl" label="View quotation" />
@endif
@if (filled($whatsappUrl))
<x-email::blocks.spacer :height="32" />
<x-email::blocks.whatsapp-button :url="$whatsappUrl" prompt="Want to talk it through?" />
@endif
<x-email::blocks.signature />
</x-email::layouts.client>
