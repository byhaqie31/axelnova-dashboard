@component('mail::message')
# Thanks for the referral, {{ $referral->referrer_name }}

I've received your referral of **{{ $referral->business_name }}** and made a note of it. I'll reach out to them (usually within 3 business days) and keep you posted on how it goes.

If it becomes a signed, paid project, your commission follows per the Partner Program terms (your current tier is **{{ $referral->commission_tier_pct }}%** of the final project value).

Thanks for thinking of me. It genuinely means a lot.

Best,

**Ahmad Baihaqie**<br>
Founder, Axel Nova Ventures<br>
baihaqie@axelnova.tech
@endcomponent
