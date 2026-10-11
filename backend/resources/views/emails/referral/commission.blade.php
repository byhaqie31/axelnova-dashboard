{{-- Email 17 — commission earned. Copy kept from the original template. --}}
@php
    $heading = "Good news, {$name} — your referral converted";
@endphp
<x-email::layouts.client title="Your referral commission" :preheader="'Your estimated commission: '.$commission.'.'">
<x-email::blocks.heading-intro eyebrow="Commission" :heading="$heading">
Your referral of {{ $businessName }} has turned into a signed project. Thank you — this is exactly what the Partner Program is for.
</x-email::blocks.heading-intro>
<x-email::blocks.paragraph>Based on your {{ $ratePct }}% rate on the project value (capped at {{ $cap }} per referral), your estimated commission is:</x-email::blocks.paragraph>
<x-email::blocks.spacer :height="24" />
<x-email::blocks.highlight-panel label="Estimated commission" :amount="$commission" note="This is an estimate against the agreed project value and may settle slightly once the project is finalised." />
<x-email::blocks.paragraph>To get you paid, just reply to this email with your bank details:</x-email::blocks.paragraph>
<x-email::blocks.spacer :height="16" />
<x-email::blocks.line-items :items="[['label' => 'Bank name'], ['label' => 'Account number'], ['label' => 'Account holder name']]" />
<x-email::blocks.paragraph>Once I have those, I'll arrange the transfer and send you a confirmation.</x-email::blocks.paragraph>
<x-email::blocks.paragraph>Thanks again for the introduction.</x-email::blocks.paragraph>
<x-email::blocks.signature signoff="Best," />
</x-email::layouts.client>
