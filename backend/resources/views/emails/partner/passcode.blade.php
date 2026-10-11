{{-- Email 18 — Partner Portal access. Copy kept from the original template (Phase 2 replaces the passcode with a set-up link). --}}
<x-email::layouts.client title="Your Partner Portal access" preheader="Your Partner Portal sign-in details.">
<x-email::blocks.heading-intro eyebrow="Partner Portal" heading="Your Partner Portal access is ready">
Hi {{ $name }}, your partner account with Axel Nova Ventures is ready. You can now sign in to the Partner Portal.
</x-email::blocks.heading-intro>
<x-email::blocks.paragraph>Use these details to log in:</x-email::blocks.paragraph>
<x-email::blocks.spacer :height="16" />
<x-email::blocks.summary-table :rows="[['Email', $email], ['Passcode', $passcode]]" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$loginUrl" label="Open the Partner Portal" />
<x-email::blocks.paragraph>For your security, keep this passcode private. We never display it anywhere else, so store it somewhere safe. If you ever lose it, use "Forgot passcode" on the login page and a fresh one will be emailed to you.</x-email::blocks.paragraph>
<x-email::blocks.paragraph>Thanks for partnering with us.</x-email::blocks.paragraph>
<x-email::blocks.signature signoff="Best," />
</x-email::layouts.client>
