{{-- Email 28 — owner alert: a client/partner/team email bounced, was marked as spam, or failed (kit: 28-delivery-alert.html = bounced). --}}
@php
    $kind = strtolower($emailLabel);
    $article = preg_match('/^[aeiou]/i', $kind) ? 'An' : 'A';
    [$heading, $lead, $label, $title, $fix] = match ($problem) {
        'complained' => [
            "{$article} {$kind} email was marked as spam",
            "The {$audience} marked an email as spam. Further emails to this address may not arrive.",
            'Marked as spam',
            "{$recordLabel} was marked as spam by the {$audience}",
            "Check in with the {$audience} directly before emailing them again.",
        ],
        'failed' => [
            "{$article} {$kind} email failed to send",
            'An email could not be sent after every retry. Check the reason, then resend.',
            'Failed',
            "{$recordLabel} was not sent",
            'Check the reason below, then resend from the record.',
        ],
        default => [
            "{$article} {$kind} email bounced",
            "A {$audience} email did not arrive. Fix the address and resend so the {$audience} is not left waiting.",
            'Bounced',
            "{$recordLabel} did not reach the {$audience}",
            "Confirm the address with the {$audience}, update it, then resend.",
        ],
    };
    $preheader = "{$recordLabel} ".match ($problem) {
        'complained' => 'was marked as spam by',
        'failed' => 'failed to send to',
        default => 'bounced for',
    }." {$recipient}.";
@endphp
<x-email::layouts.internal title="Email delivery problem" :preheader="$preheader">
<x-email::blocks.heading-intro-internal eyebrow="Email delivery problem" :heading="$heading">
{{ $lead }}
</x-email::blocks.heading-intro-internal>
<x-email::blocks.spacer :height="20" />
<x-email::blocks.warning-box :label="$label" :title="$title" :text="filled($reason) ? 'Reason: '.$reason.'.' : null" />
<x-email::blocks.spacer :height="24" />
<x-email::blocks.summary-table :rows="[
    ['Email', $emailLabel],
    ['Recipient', $recipient],
    ['Sent', $sentAt],
    ['Problem', $label],
    ['Fix', $fix],
]" />
@if (filled($recordUrl))
<x-email::blocks.spacer :height="24" />
<x-email::blocks.main-button :url="$recordUrl" label="Open record" />
@endif
</x-email::layouts.internal>
