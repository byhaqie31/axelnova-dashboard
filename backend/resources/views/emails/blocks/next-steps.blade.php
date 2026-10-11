{{-- Kit block: next-steps. :steps = [['title' => …, 'text' => …], …] — numbered 01…; step 1 carries the ink rule. --}}
@props(['steps'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr>
@foreach (array_values($steps) as $i => $step)
@if ($i > 0)
    <td class="step-gap" width="3.5%" style="font-size: 0; line-height: 0;">&nbsp;</td>
@endif
    <td class="stack step" width="31%" valign="top" style="padding: 16px 0 0 0; border-top: 1px solid {{ $i === 0 ? '#14161A' : '#E6E8EC' }};">
      <p style="margin: 0; font-family: 'Geist Mono', ui-monospace, Menlo, Consolas, monospace; font-size: 11px; line-height: 16px; letter-spacing: 0.08em; color: #5E646E;">{{ sprintf('%02d', $i + 1) }}</p>
      <p style="margin: 8px 0 0 0; font-size: 15px; line-height: 22px; font-weight: 600; color: #14161A;">{{ $step['title'] }}</p>
      <p style="margin: 4px 0 0 0; font-size: 14px; line-height: 20px; color: #5E646E;">{{ $step['text'] }}</p>
    </td>
@endforeach
  </tr>
</table>
