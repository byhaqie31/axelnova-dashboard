{{-- Email 19 — confirm a partner passcode reset before anything changes (new; flow in Phase 2). --}}
<x-email::layouts.client title="Confirm your passcode reset" preheader="Confirm it was you before a new passcode is issued.">
<x-email::blocks.heading-intro eyebrow="Partner Portal" heading="Confirm your passcode reset">
Hi {{ $name }}, someone asked to reset the passcode for your Partner Portal account. If it was you, confirm below and a new passcode will be emailed to you.
</x-email::blocks.heading-intro>
<x-email::blocks.spacer :height="32" />
<x-email::blocks.main-button :url="$confirmUrl" label="Confirm reset" />
<x-email::blocks.spacer :height="32" />
<x-email::blocks.warning-box label="Didn't ask for this?" title="You can ignore this email" :text="'Your current passcode keeps working. This link expires in '.$expiresIn.'.'" />
<x-email::blocks.signature signoff="Best," />
</x-email::layouts.client>
