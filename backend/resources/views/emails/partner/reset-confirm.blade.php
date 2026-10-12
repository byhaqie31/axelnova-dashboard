{{-- Email 19 — partner passcode reset link (Phase 2): nothing changes until the partner uses it. --}}
<x-email::layouts.client title="Choose a new passcode" preheader="Choose a new Partner Portal passcode.">
<x-email::blocks.heading-intro eyebrow="Partner Portal" heading="Choose a new passcode">
Hi {{ $name }}, someone asked to reset the passcode for your Partner Portal account. If it was you, choose a new one below.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$resetUrl" label="Choose a new passcode" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.warning-box label="Didn't ask for this?" title="You can ignore this email" :text="'Your current passcode keeps working. This link expires in '.$expiresIn.'.'" />
<x-email::blocks.signature signoff="Best," />
</x-email::layouts.client>
