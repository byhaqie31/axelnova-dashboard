{{-- Email 21 — team password reset. Copy kept from the original template (Phase 2 replaces the temporary password with a reset link). --}}
@php
    $heading = "Your password has been reset, {$name}";
@endphp
<x-email::layouts.client title="Your password has been reset" preheader="New sign-in details for your team account.">
<x-email::blocks.heading-intro eyebrow="Password reset" :heading="$heading">
A new temporary password has been issued for your Axel Nova Ventures team account. Any sessions that were still signed in have been closed.
</x-email::blocks.heading-intro>
<x-email::blocks.paragraph>Here's what you need to sign in:</x-email::blocks.paragraph>
<x-email::blocks.spacer :height="16" />
<x-email::blocks.summary-table :rows="[['Email', $email], ['Temporary password', $password]]" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$loginUrl" label="Sign in to the Team Portal" />
<x-email::blocks.paragraph>If you didn't ask for this reset, reply to this email straight away so we can look into it.</x-email::blocks.paragraph>
<x-email::blocks.signature :signoff="null" />
</x-email::layouts.client>
