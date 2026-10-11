{{-- Kit block: warning-box — amber notice: what happened and what to do. --}}
@props(['label', 'title', 'text' => null])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: separate; background: #FFF8EB; border: 1px solid #F0D9A8; border-radius: 12px;">
  <tr>
    <td class="cta-box" bgcolor="#FFF8EB" style="padding: 20px 24px; border-radius: 12px;">
      <p style="margin: 0; font-family: 'Geist Mono', ui-monospace, Menlo, Consolas, monospace; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; line-height: 16px; font-weight: 500; color: #7A5600;">{{ $label }}</p>
      <p style="margin: 8px 0 0 0; font-size: 15px; line-height: 22px; font-weight: 600; color: #3D2C00;">{{ $title }}</p>
@if (filled($text))
      <p style="margin: 4px 0 0 0; font-size: 14px; line-height: 20px; color: #5C4300;">{{ $text }}</p>
@endif
    </td>
  </tr>
</table>
