{{-- Kit block: highlight-panel — one big figure (an amount, a code) with a label and an optional note. --}}
@props(['label', 'amount', 'note' => null])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: separate; background: #F7F8FA; border-radius: 12px;">
  <tr>
    <td class="cta-box" bgcolor="#F7F8FA" style="padding: 24px; border-radius: 12px;">
      <p style="margin: 0; font-family: 'Geist Mono', ui-monospace, Menlo, Consolas, monospace; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; line-height: 16px; color: #5E646E;">{{ $label }}</p>
      <p class="amount" style="margin: 8px 0 0 0; font-size: 32px; line-height: 40px; font-weight: 600; letter-spacing: -0.02em; color: #14161A;">{{ $amount }}</p>
@if (filled($note))
      <p style="margin: 8px 0 0 0; font-size: 14px; line-height: 20px; color: #5E646E;">{{ $note }}</p>
@endif
    </td>
  </tr>
</table>
