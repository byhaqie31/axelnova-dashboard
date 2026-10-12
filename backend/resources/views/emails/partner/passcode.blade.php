{{-- Email 18 — Partner Portal access: a one-time "Set up your access" link where the partner chooses their passcode (Phase 2). --}}
<x-email::layouts.client title="Your Partner Portal access" preheader="Choose your Partner Portal passcode.">
<x-email::blocks.heading-intro eyebrow="Partner Portal" heading="Your Partner Portal access is ready">
Hi {{ $name }}, your partner account with Axel Nova Ventures is ready. Choose a passcode to sign in to the Partner Portal.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$setupUrl" label="Set up your access" />
<x-email::blocks.paragraph>This link works for {{ $expiresIn }}. Your passcode is yours alone — we never see it or send it by email. If you ever forget it, use "Forgot passcode" on the login page.</x-email::blocks.paragraph>
<x-email::blocks.paragraph>Thanks for partnering with us.</x-email::blocks.paragraph>
<x-email::blocks.signature signoff="Best," />
</x-email::layouts.client>
