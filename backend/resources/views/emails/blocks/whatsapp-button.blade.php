{{-- Kit block: whatsapp-button — a bordered prompt card with the button on the right (stacks on mobile). --}}
@props([
    'url',
    'prompt',
    'text' => 'The quickest way to reach me is a WhatsApp message.',
    'label' => 'Chat on WhatsApp',
])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: separate; border: 1px solid #E6E8EC; border-radius: 12px;">
  <tr>
    <td class="cta-box" style="padding: 24px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td class="stack" valign="middle" style="padding: 0;">
            <p style="margin: 0; font-size: 15px; line-height: 22px; font-weight: 600; color: #14161A;">{{ $prompt }}</p>
            <p style="margin: 4px 0 0 0; font-size: 14px; line-height: 20px; color: #5E646E;">{{ $text }}</p>
          </td>
          <td class="stack cta-btn-cell" valign="middle" align="right" style="padding: 0 0 0 20px;">
            <x-email::blocks.main-button :url="$url" :label="$label" />
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
