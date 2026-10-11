{{-- Email 5 — order confirmed on quote acceptance (new; mailable + trigger in Phase 3). --}}
@php
    $heading = "You're all set, {$name}";
@endphp
<x-email::layouts.client title="Order confirmed" :preheader="'Order '.$orderNumber.' is confirmed. Here\'s what happens next.'">
<x-email::blocks.heading-intro eyebrow="Order confirmed" :heading="$heading">
Thanks for accepting the quotation. Your order is confirmed and I'm getting everything ready on my side.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.order-tracker :current="1" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.summary-table :rows="[
    ['Order', $orderNumber],
    ['Package', $packageName],
    ['Total', $total],
    ['Deposit', $depositAmount],
    ['Deposit due', $depositDue],
]" />
<x-email::blocks.spacer :height="40" lg />
<x-email::blocks.next-steps :steps="[
    ['title' => 'Deposit invoice', 'text' => 'It follows in a separate email.'],
    ['title' => 'Kick-off', 'text' => 'Work starts once the deposit is in.'],
    ['title' => 'Build', 'text' => 'I keep you posted at every stage.'],
]" />
@if (filled($quotationUrl))
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$quotationUrl" label="View quotation" />
@endif
<x-email::blocks.signature />
</x-email::layouts.client>
