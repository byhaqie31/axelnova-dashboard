{{-- Kit: body paragraph for anything that needs a full sentence. Slot = HTML-safe content. --}}
@props(['margin' => '32px 0 0 0'])
<p style="margin: {{ $margin }}; font-size: 16px; line-height: 26px; color: #3A3F47;">{!! trim((string) $slot) !!}</p>
