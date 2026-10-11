{{-- Email 20 — team welcome. Copy kept from the original template (Phase 2 replaces the temporary password with a set-password link). --}}
@php
    $heading = "Welcome to the team, {$name}!";
@endphp
<x-email::layouts.client title="Welcome to the team" preheader="Your Axel Nova Ventures team account is ready.">
<x-email::blocks.heading-intro eyebrow="Welcome" :heading="$heading">
I'm genuinely glad to have you with us at Axel Nova Ventures. This isn't just another account being switched on — it's the start of something we get to build together.
</x-email::blocks.heading-intro>
<x-email::blocks.paragraph>We're a small team with big intentions: to do work we're proud of, treat every client like they matter, and keep raising our own bar. There will be busy days and hard problems, but that's exactly where good teams are made. Bring your ideas, ask the bold questions, and don't be afraid to make things better than you found them.</x-email::blocks.paragraph>
<x-email::blocks.paragraph>Let's strive together, grow together, and turn every project into something we can point at and say — <em>we made that.</em></x-email::blocks.paragraph>
<x-email::blocks.paragraph>Here's what you need to sign in:</x-email::blocks.paragraph>
<x-email::blocks.spacer :height="16" />
<x-email::blocks.summary-table :rows="[['Email', $email], ['Temporary password', $password]]" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$loginUrl" label="Sign in to the Team Portal" />
<x-email::blocks.paragraph>For your security, please change your password after your first sign-in and keep it private. This temporary password won't be shown again.</x-email::blocks.paragraph>
<x-email::blocks.signature signoff="Welcome aboard — let's do great things." />
</x-email::layouts.client>
