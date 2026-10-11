{{-- Email 25 — owner alert: new inquiry. Copy kept from the original template. Reply-To is the inquirer. --}}
@php
    $eyebrow = match ($origin) {
        'contact' => 'Contact form',
        'partner' => 'Partner hire',
        'collaboration' => 'Collaboration',
        default => 'Quote form',
    };
@endphp
<x-email::layouts.internal title="New inquiry" :preheader="$name.' — '.$subjectLabel">
<x-email::blocks.heading-intro-internal :eyebrow="$eyebrow" heading="New inquiry">
@if ($origin === 'contact')
A new message came in through the contact form.
@elseif ($origin === 'partner')
A client wants to hire {{ $partnerName }} through Axel Nova, from the collaboration page. Log the lead, then pass it on.
@elseif ($origin === 'collaboration')
A studio applied to partner with Axel Nova through the collaborations page.
@else
A new project inquiry came in through the quote form.
@endif
</x-email::blocks.heading-intro-internal>
<x-email::blocks.spacer :height="20" />
<x-email::blocks.summary-table :rows="$rows" />
<x-email::blocks.section-label text="Message" />
<x-email::blocks.paragraph margin="0">{!! nl2br(e($inquiryMessage)) !!}</x-email::blocks.paragraph>
<x-email::blocks.spacer :height="24" />
<x-email::blocks.main-button :url="$adminUrl" label="Open in Admin" />
<x-email::blocks.paragraph>Reply to this email to answer {{ $name }} directly.</x-email::blocks.paragraph>
</x-email::layouts.internal>
