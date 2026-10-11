{{-- Email 24 — owner alert: new quote-builder lead. Copy kept from the original template. --}}
<x-email::layouts.internal title="New quote request" :preheader="$reference.' · '.$name.' · '.$estimate">
<x-email::blocks.heading-intro-internal eyebrow="Quote builder" heading="New Quote Request">
A new lead has been submitted via the quote builder.
</x-email::blocks.heading-intro-internal>
<x-email::blocks.spacer :height="20" />
<x-email::blocks.summary-table :rows="[
    ['Reference', $reference],
    ['Name', $name],
    ['Email', $email],
    ['Phone', $phone],
    ['Company', $company],
    ['Package', $packageName],
    ['Estimate', $estimate],
    ['Timeline', $timeline],
    ['Submitted', $submitted],
]" />
<x-email::blocks.spacer :height="24" />
<x-email::blocks.main-button :url="$adminUrl" label="View Lead in Admin" />
</x-email::layouts.internal>
