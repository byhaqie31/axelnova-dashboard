{{-- Kit block: heading-intro (internal layout). Slot = the lead paragraph. --}}
@props(['eyebrow', 'heading'])
<p style="margin: 0; font-family: 'Geist Mono', ui-monospace, Menlo, Consolas, monospace; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; line-height: 16px; font-weight: 500; color: #5E646E;">{{ $eyebrow }}</p>
<h1 style="margin: 10px 0 0 0; font-size: 24px; line-height: 30px; font-weight: 600; letter-spacing: -0.02em; color: #14161A;">{{ $heading }}</h1>
<p style="margin: 10px 0 0 0; font-size: 15px; line-height: 24px; color: #3A3F47;">{!! trim((string) $slot) !!}</p>
