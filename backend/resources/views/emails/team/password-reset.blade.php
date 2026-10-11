{{-- Email 21 — team password reset link (Phase 2): self-service or sent by the founder. --}}
@php
    $heading = "Reset your password, {$name}";
@endphp
<x-email::layouts.client title="Reset your password" preheader="Choose a new password for your team account.">
<x-email::blocks.heading-intro eyebrow="Password reset" :heading="$heading">
Use the button below to choose a new password for your Axel Nova Ventures team account.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$resetUrl" label="Choose a new password" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.warning-box label="Didn't ask for this?" title="You can ignore this email" :text="'Your current password keeps working until you choose a new one. This link expires in '.$expiresIn.'.'" />
<x-email::blocks.signature :signoff="null" />
</x-email::layouts.client>
