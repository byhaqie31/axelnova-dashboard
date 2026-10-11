{{-- Recomposes docs/global/email-kit/00-blocks.html from our components (KitBlocksFidelityTest). --}}
<x-email::layouts.client title="Axel Nova email blocks" preheader="Every reusable block in the client layout, in one file.">
<x-email::blocks.heading-intro eyebrow="Eyebrow label" heading="Heading goes here">
Intro paragraph. One or two sentences that say what this email is and what happens next.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="40" lg />
<x-email::blocks.next-steps :steps="[
    ['title' => 'Step title', 'text' => 'Short supporting line.'],
    ['title' => 'Step title', 'text' => 'Short supporting line.'],
    ['title' => 'Step title', 'text' => 'Short supporting line.'],
]" />
<x-email::blocks.spacer :height="40" lg />
<x-email::blocks.order-tracker :current="2" />
<x-email::blocks.spacer :height="40" lg />
<x-email::blocks.summary-table :rows="[
    ['Label', 'Value'],
    ['Label', 'Value'],
    ['Label', 'A longer value that wraps onto a second line when it needs to'],
]" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.highlight-panel label="Panel label" amount="RM 0,000" note="Supporting note" />
<x-email::blocks.section-label text="Section label" />
<x-email::blocks.line-items :items="[
    ['label' => 'Line item', 'detail' => 'Optional detail line', 'amount' => 'RM 0,000'],
    ['label' => 'Line item', 'amount' => 'RM 000'],
]" :total="['label' => 'Total', 'amount' => 'RM 0,000']" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button url="https://example.com/" label="Main button" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.whatsapp-button url="https://wa.me/" prompt="Prompt line goes here" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.warning-box label="Warning label" title="Warning title" text="Supporting line that says what happened and what to do." />
<x-email::blocks.paragraph>Body paragraph for anything that needs a full sentence.</x-email::blocks.paragraph>
<x-email::blocks.signature />
</x-email::layouts.client>
