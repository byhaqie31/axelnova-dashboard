{{-- Kit block: main-button — real text on an ink fill, arrow appended. --}}
@props(['url', 'label'])
<table role="presentation" class="btn" cellpadding="0" cellspacing="0" border="0" style="border-collapse: separate;">
  <tr>
    <td align="center" bgcolor="#14161A" style="background: #14161A; border-radius: 8px;">
      <a href="{{ $url }}" target="_blank" style="display: inline-block; padding: 14px 22px; font-family: 'Geist', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 20px; font-weight: 500; color: #FFFFFF; text-decoration: none; white-space: nowrap; border-radius: 8px;">{{ $label }}&nbsp;&rarr;</a>
    </td>
  </tr>
</table>
