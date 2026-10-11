{{-- Kit layout: client (docs/global/email-kit/00-blocks.html shell). Accent bar, logo header, company footer. --}}
@props(['title', 'preheader'])
@php
    $accent = config('email_design.accent');
    $company = config('email_design.company');
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light only">
<meta name="supported-color-schemes" content="light only">
<title>{{ $title }}</title>
<!-- Geist loads in Apple Mail and a few others. Gmail and Outlook fall back to the system stack. -->
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
  table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
  body { margin: 0 !important; padding: 0 !important; width: 100% !important; background: #FFFFFF; }
  a { color: {{ $accent }}; }

  @media only screen and (max-width: 620px) {
    .container { width: 100% !important; }
    .px { padding-left: 24px !important; padding-right: 24px !important; }
    .pt-top { padding-top: 32px !important; }
    .h1 { font-size: 28px !important; line-height: 34px !important; }
    .lead { font-size: 16px !important; line-height: 26px !important; }
    .amount { font-size: 28px !important; line-height: 34px !important; }
    .gap-lg { height: 32px !important; }
    .stack { display: block !important; width: 100% !important; box-sizing: border-box !important; }
    .step { padding: 14px 0 16px 0 !important; }
    .step-gap { display: none !important; }
    .label { width: 108px !important; }
    .track-label { font-size: 12px !important; line-height: 18px !important; }
    .cta-box { padding: 20px !important; }
    .cta-btn-cell { padding: 16px 0 0 0 !important; }
    .btn { width: 100% !important; }
    .btn a { display: block !important; text-align: center !important; }
    .m-hide { display: none !important; }
    .m-block { display: block !important; }
  }
</style>
</head>
<body style="margin: 0; padding: 0; background: #FFFFFF;">

<div style="display: none; max-height: 0; overflow: hidden; mso-hide: all; font-size: 1px; line-height: 1px; color: #FFFFFF;">{{ $preheader }}</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #FFFFFF;">
<tr>
<td align="center" style="padding: 0;">

<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" align="center"><tr><td><![endif]-->
<table role="presentation" class="container" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px; margin: 0 auto;">
  <tr>
    <td height="3" bgcolor="{{ $accent }}" style="height: 3px; background: {{ $accent }}; font-size: 0; line-height: 0;">&nbsp;</td>
  </tr>
  <tr>
    <td class="px pt-top" style="padding: 48px 40px 48px 40px; font-family: 'Geist', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color: #14161A;">

      <x-email::blocks.logo-header />
      <x-email::blocks.spacer :height="48" lg />

{{ $slot }}

      <x-email::blocks.spacer :height="48" lg />
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td style="padding: 20px 0 0 0; border-top: 1px solid #E6E8EC; font-size: 12px; line-height: 20px; color: #5E646E;">
            {{ $company['name'] }} ({{ $company['registration'] }})<span class="m-hide"> &middot; </span><span class="m-block">{{ $company['location'] }}</span><br class="m-hide">
            &copy; {{ now()->year }} {{ $company['name'] }}. All rights reserved.
          </td>
        </tr>
      </table>

    </td>
  </tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->

</td>
</tr>
</table>

</body>
</html>

