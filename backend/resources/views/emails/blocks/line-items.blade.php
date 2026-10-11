{{--
  Kit block: line-items. :items = [['label' => …, 'detail' => ?, 'amount' => ?], …].
  Priced when any item has an amount (label | amount columns); otherwise the
  kit's single-column colspan="2" list. :total = ['label' => …, 'amount' => …] or null.
--}}
@props(['items', 'total' => null])
@php
    $items = array_values($items);
    $priced = collect($items)->contains(fn (array $item): bool => filled($item['amount'] ?? null));
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
@foreach ($items as $i => $item)
@php $top = $i === 0 ? 'border-top: 1px solid #E6E8EC; ' : ''; @endphp
  <tr>
@if ($priced)
    <td valign="top" style="padding: 13px 0; {{ $top }}border-bottom: 1px solid #E6E8EC; font-size: 15px; line-height: 20px; font-weight: 500; color: #14161A;">{{ $item['label'] }}@if (filled($item['detail'] ?? null))<br><span style="font-size: 13px; line-height: 20px; font-weight: 400; color: #5E646E;">{{ $item['detail'] }}</span>@endif</td>
    <td valign="top" align="right" style="padding: 13px 0 13px 16px; {{ $top }}border-bottom: 1px solid #E6E8EC; font-size: 15px; line-height: 20px; font-weight: 500; color: #14161A; white-space: nowrap;">{{ $item['amount'] ?? '' }}</td>
@else
    <td colspan="2" valign="top" style="padding: 13px 0; {{ $top }}border-bottom: 1px solid #E6E8EC; font-size: 15px; line-height: 20px; font-weight: 500; color: #14161A;">{{ $item['label'] }}</td>
@endif
  </tr>
@endforeach
@if ($total)
  <tr>
    <td valign="top" style="padding: 14px 0 0 0; font-size: 16px; line-height: 22px; font-weight: 600; color: #14161A;">{{ $total['label'] }}</td>
    <td valign="top" align="right" style="padding: 14px 0 0 16px; font-size: 16px; line-height: 22px; font-weight: 600; color: #14161A; white-space: nowrap;">{{ $total['amount'] }}</td>
  </tr>
@endif
</table>
