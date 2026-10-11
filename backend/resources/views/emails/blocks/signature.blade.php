{{-- Kit block: signature — the founder's sign-off. :signoff="null" drops the closing line. --}}
@props(['signoff' => 'Talk soon,'])
@if (filled($signoff))
<p style="margin: 32px 0 0 0; font-size: 16px; line-height: 26px; color: #3A3F47;">{{ $signoff }}</p>
@endif
<p style="margin: {{ filled($signoff) ? '14px' : '32px' }} 0 0 0; font-size: 16px; line-height: 24px; font-weight: 600; color: #14161A;">{{ config('email_design.signature.name') }}</p>
<p style="margin: 2px 0 0 0; font-size: 14px; line-height: 22px; color: #5E646E;">{{ config('email_design.signature.title') }}</p>
<p style="margin: 2px 0 0 0; font-size: 14px; line-height: 22px;"><a href="mailto:{{ config('email_design.signature.email') }}" style="color: {{ config('email_design.accent') }}; text-decoration: none;">{{ config('email_design.signature.email') }}</a></p>
