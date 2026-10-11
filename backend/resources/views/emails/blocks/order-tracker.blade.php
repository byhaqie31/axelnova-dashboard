{{--
  Kit block: order-tracker. :current = the stage the order is at (1 Confirmed …
  4 Completed); stages before it read "Done", it reads "Now". Pass 5 when the
  whole journey is finished (every stage "Done").
--}}
@props(['current'])
@php
    $stages = ['Confirmed', 'In progress', 'Delivered', 'Completed'];
    $accent = config('email_design.accent');
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr>
@foreach ($stages as $i => $stage)
@php
    $n = $i + 1;
    $done = $n < $current;
    $now = $n === (int) $current;
@endphp
@if ($i > 0)
    <td width="2%" style="font-size: 0; line-height: 0;">&nbsp;</td>
@endif
    <td width="23.5%" valign="top" style="padding: 12px 0 0 0; border-top: 2px solid {{ $done ? '#14161A' : ($now ? $accent : '#E6E8EC') }};">
      <p style="margin: 0; font-family: 'Geist Mono', ui-monospace, Menlo, Consolas, monospace; font-size: 10px; line-height: 16px; letter-spacing: 0.06em; text-transform: uppercase; color: #5E646E;">{{ sprintf('%02d', $n) }}{!! $done ? ' &middot; Done' : ($now ? ' &middot; Now' : '') !!}</p>
      <p class="track-label" style="margin: 6px 0 0 0; font-size: 14px; line-height: 20px; font-weight: {{ $done || $now ? '600' : '500' }}; color: {{ $done || $now ? '#14161A' : '#5E646E' }};">{{ $stage }}</p>
    </td>
@endforeach
  </tr>
</table>
