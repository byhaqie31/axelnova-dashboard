{{--
  Kit block: summary-table. :rows = [[label, value], …] — a list of pairs (a
  label may repeat). Rows whose value is null or '' are skipped; the first row
  that renders carries the top rule.
--}}
@props(['rows'])
@php
    $rows = array_values(array_filter($rows, fn (array $row): bool => $row[1] !== null && $row[1] !== ''));
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
@foreach ($rows as $i => [$label, $value])
@php $top = $i === 0 ? 'border-top: 1px solid #E6E8EC; ' : ''; @endphp
  <tr>
    <td class="label" width="128" valign="top" style="width: 128px; padding: 13px 12px 13px 0; {{ $top }}border-bottom: 1px solid #E6E8EC; font-family: 'Geist Mono', ui-monospace, Menlo, Consolas, monospace; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; line-height: 20px; color: #5E646E;">{{ $label }}</td>
    <td valign="top" style="padding: 13px 0; {{ $top }}border-bottom: 1px solid #E6E8EC; font-size: 15px; line-height: 20px; font-weight: 500; color: #14161A;">{{ $value }}</td>
  </tr>
@endforeach
</table>
