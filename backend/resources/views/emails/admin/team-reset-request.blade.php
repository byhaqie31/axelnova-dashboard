{{-- Email 27 — owner alert: a team member asked for a password reset. Copy kept from the original template. --}}
<x-email::layouts.internal title="Team password reset request" :preheader="$userName.' is waiting on a password reset.'">
<x-email::blocks.heading-intro-internal eyebrow="Team workspace" heading="Team password reset request">
{{ $userName }} used "forgot password" on the team workspace sign-in and is waiting on you — team passwords can only be reset by you.
</x-email::blocks.heading-intro-internal>
<x-email::blocks.spacer :height="20" />
<x-email::blocks.summary-table :rows="$rows" />
<x-email::blocks.paragraph>Open the Users screen and use <strong>Reset password</strong> on their row — a new temporary password is emailed to them and shown to you once.</x-email::blocks.paragraph>
<x-email::blocks.spacer :height="24" />
<x-email::blocks.main-button :url="$usersUrl" label="Open Users" />
</x-email::layouts.internal>
