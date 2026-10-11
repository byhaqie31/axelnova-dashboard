{{-- Email 27 — owner heads-up: a teammate set a new password through a reset link (Phase 2). --}}
<x-email::layouts.internal title="Team password changed" :preheader="$userName.' set a new team password.'">
<x-email::blocks.heading-intro-internal eyebrow="Team workspace" heading="Team password changed">
{{ $userName }} set a new password through a reset link.
</x-email::blocks.heading-intro-internal>
<x-email::blocks.spacer :height="20" />
<x-email::blocks.summary-table :rows="$rows" />
<x-email::blocks.paragraph>No action needed. If it wasn't them, deactivate the account from the Users screen.</x-email::blocks.paragraph>
<x-email::blocks.spacer :height="24" />
<x-email::blocks.main-button :url="$usersUrl" label="Open Users" />
</x-email::layouts.internal>
