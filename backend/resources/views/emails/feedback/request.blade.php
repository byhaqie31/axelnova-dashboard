{{-- Email 4 — feedback request (manual only). Copy kept from the original template. --}}
@php
    $heading = 'How did we do'.(filled($name) ? ", {$name}" : '').'?';
@endphp
<x-email::layouts.client title="How did we do?" preheader="A short form — a couple of quick ratings and two open questions.">
<x-email::blocks.heading-intro eyebrow="Feedback" :heading="$heading">
@if ($variant === 'project_label')
Your project — {{ $projectLabel }} — has wrapped, and I'd love to hear how the experience was for you.
@elseif ($variant === 'project')
Your project with Axel Nova Ventures has wrapped, and I'd love to hear how the experience was for you.
@else
I'd love to hear your honest take on Axel Nova Ventures — how we come across, and how the experience of dealing with us has been.
@endif
</x-email::blocks.heading-intro>
<x-email::blocks.paragraph>It's a short form — a couple of quick ratings and two open questions. Two minutes, tops.</x-email::blocks.paragraph>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$feedbackUrl" label="Share your feedback" />
<x-email::blocks.paragraph>Your answers come straight to me. If you're happy for your words to appear on the Axel Nova site, there's an optional permission toggle on the form — nothing is ever published without it.</x-email::blocks.paragraph>
<x-email::blocks.signature signoff="Thanks for building with me," />
</x-email::layouts.client>
