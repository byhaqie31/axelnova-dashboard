@component('mail::message')
# Good news, {{ $referral->referrer_name }} — your referral converted

Your referral of **{{ $referral->business_name }}** has turned into a signed project. Thank you — this is exactly what the Partner Program is for.

Based on your **{{ $referral->commission_tier_pct }}%** tier of the project value, your estimated commission is:

@component('mail::panel')
**RM {{ number_format($commission, 2) }}**
@endcomponent

This is an estimate against the agreed project value and may settle slightly once the project is finalised.

**To get you paid, just reply to this email with your bank details:**

- Bank name
- Account number
- Account holder name

Once I have those, I'll arrange the transfer and send you a confirmation.

Thanks again for the introduction.

Best,

**Ahmad Baihaqie**<br>
Founder, Axel Nova Ventures<br>
baihaqie@axelnova.tech
@endcomponent
