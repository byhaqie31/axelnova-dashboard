{{-- Email 2 — inquiry auto-reply (kit: 02-inquiry-received.html = the contact variant). --}}
@php
    $lead = match ($variant) {
        'partner' => "I've received your enquiry for Axel Nova × {$partnerName}. I'll review it with "
            .(filled($partnerFounder) ? "{$partnerFounder} at {$partnerName}" : "the {$partnerName} team")
            .' and get back to you with next steps, usually within 1–2 business days.',
        'collaboration' => "Thanks for your interest in collaborating with Axel Nova Ventures. I'll look through your work and reply personally, usually within a few working days.",
        'project' => "I've received your project inquiry and made a note of it. I'll review the details and get back to you with a tailored quote, usually within 1–2 business days.",
        default => "I've received your message and will get back to you personally, usually within one working day.",
    };
    $eyebrow = match ($variant) {
        'partner' => 'Partner enquiry',
        'collaboration' => 'Collaboration',
        'project' => 'Project inquiry',
        default => 'Message received',
    };
    $replyWithin = match ($variant) {
        'collaboration' => 'Usually within a few working days.',
        'project', 'partner' => 'Usually within 1–2 business days.',
        default => 'Usually within one working day.',
    };
    $closing = $variant === 'project'
        ? "In the meantime, just reply to this email with anything else that'll help me scope your project."
        : "If there's anything you'd like to add, just reply to this email.";
    $heading = "Thanks for reaching out, {$name}";
@endphp
<x-email::layouts.client title="Thanks for reaching out" :preheader="$lead">
<x-email::blocks.heading-intro :eyebrow="$eyebrow" :heading="$heading">
{{ $lead }}
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="40" lg />
<x-email::blocks.next-steps :steps="[
    ['title' => 'Received', 'text' => 'Your message is in my inbox.'],
    ['title' => 'Read personally', 'text' => 'I read every message myself.'],
    ['title' => 'Reply', 'text' => $replyWithin],
]" />
<x-email::blocks.spacer :height="40" lg />
<x-email::blocks.summary-table :rows="$rows" />
@if (filled($whatsappUrl))
<x-email::blocks.spacer :height="32" />
<x-email::blocks.whatsapp-button :url="$whatsappUrl" prompt="Want to talk it through sooner?" />
@endif
<x-email::blocks.paragraph>{{ $closing }}</x-email::blocks.paragraph>
<x-email::blocks.signature />
</x-email::layouts.client>
