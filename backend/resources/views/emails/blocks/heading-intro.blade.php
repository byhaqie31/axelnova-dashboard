{{-- Kit block: heading-intro (client layout). Slot = the lead paragraph. --}}
@props(['eyebrow', 'heading'])
<p style="margin: 0; font-family: 'Geist Mono', ui-monospace, Menlo, Consolas, monospace; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; line-height: 16px; font-weight: 500; color: {{ config('email_design.accent') }};">{{ $eyebrow }}</p>
<h1 class="h1" style="margin: 14px 0 0 0; font-size: 32px; line-height: 40px; font-weight: 600; letter-spacing: -0.025em; color: #14161A;">{{ $heading }}</h1>
<p class="lead" style="margin: 18px 0 0 0; font-size: 17px; line-height: 28px; color: #3A3F47;">{!! trim((string) $slot) !!}</p>
