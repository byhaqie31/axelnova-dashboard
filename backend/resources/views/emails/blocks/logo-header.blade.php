{{-- Client header: the PNG mark + company name (replaces the kit's text-only wordmark — spec §5.13). --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0">
  <tr>
    <td valign="middle" style="padding: 0 10px 0 0;"><img src="{{ config('email_design.logo.url') }}" width="{{ config('email_design.logo.width') }}" height="{{ config('email_design.logo.height') }}" alt="" style="display: block; border: 0; outline: none; text-decoration: none; width: {{ config('email_design.logo.width') }}px; height: {{ config('email_design.logo.height') }}px;"></td>
    <td valign="middle" style="font-size: 15px; line-height: 22px; font-weight: 600; letter-spacing: -0.01em; color: #14161A;">{{ config('email_design.company.name') }}</td>
  </tr>
</table>
