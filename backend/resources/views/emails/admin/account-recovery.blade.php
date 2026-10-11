{{-- Email 32 — founder account recovery link (new; flow in Phase 2). --}}
<x-email::layouts.internal title="Reset your admin password" preheader="A password reset was requested for the admin account.">
<x-email::blocks.heading-intro-internal eyebrow="Account security" heading="Reset your admin password">
Someone asked to reset the password for the Axel Nova admin account. If it was you, use the button below.
</x-email::blocks.heading-intro-internal>
<x-email::blocks.spacer :height="24" />
<x-email::blocks.main-button :url="$resetUrl" label="Reset password" />
<x-email::blocks.spacer :height="24" />
<x-email::blocks.warning-box label="Didn't ask for this?" title="Ignore this email" :text="'Your password stays the same. This link expires in '.$expiresIn.'.'" />
</x-email::layouts.internal>
