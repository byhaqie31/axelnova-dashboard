{{-- Email 30 — owner alert: a client submitted feedback (new; mailable + trigger in Phase 4). --}}
<x-email::layouts.internal title="New feedback" :preheader="$name.' rated the experience '.$overall.'/5.'">
<x-email::blocks.heading-intro-internal eyebrow="Feedback" :heading="'New feedback from '.$name">
A client submitted the feedback form. It's pending — nothing publishes until you approve it, and only with their consent.
</x-email::blocks.heading-intro-internal>
<x-email::blocks.spacer :height="20" />
<x-email::blocks.summary-table :rows="$rows" />
@if (filled($praise))
<x-email::blocks.section-label text="What went well" />
<x-email::blocks.paragraph margin="0">{!! nl2br(e($praise)) !!}</x-email::blocks.paragraph>
@endif
@if (filled($improve))
<x-email::blocks.section-label text="What could be better" />
<x-email::blocks.paragraph margin="0">{!! nl2br(e($improve)) !!}</x-email::blocks.paragraph>
@endif
<x-email::blocks.spacer :height="24" />
<x-email::blocks.main-button :url="$adminUrl" label="Review feedback" />
</x-email::layouts.internal>
