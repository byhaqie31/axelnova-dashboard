{{-- Email 16 — referral status update to the partner (new; mailable + trigger in Phase 4). --}}
@php
    $lead = match ($status) {
        'contacted' => "I've reached out to {$businessName} and introduced myself. I'll let you know how the conversation goes.",
        'qualified' => "{$businessName} looks like a good fit, and we're now talking through their project in detail.",
        'converted' => "Great news — {$businessName} has signed on. Your commission details follow in a separate email.",
        'rejected' => "{$businessName} has decided not to go ahead for now. Thank you for the introduction all the same — it genuinely means a lot.",
        default => "There's an update on your referral of {$businessName}.",
    };
@endphp
<x-email::layouts.client title="Referral update" :preheader="$lead">
<x-email::blocks.heading-intro eyebrow="Referral update" :heading="'An update on '.$businessName">
{{ $lead }}
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.summary-table :rows="[
    ['Business', $businessName],
    ['Status', $statusLabel],
]" />
@if (filled($portalUrl))
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$portalUrl" label="Open the Partner Portal" />
@endif
<x-email::blocks.signature signoff="Best," />
</x-email::layouts.client>
