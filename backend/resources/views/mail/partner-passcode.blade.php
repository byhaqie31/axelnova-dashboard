@component('mail::message')
# Your Partner Portal access is ready

Hi {{ $referrer->name }},

Your referral partner account with **Axel Nova Ventures** has been approved. You can now sign in to track your referrals and earnings, and refer more businesses.

Use these details to log in:

- **Email:** {{ $referrer->email }}
- **Passcode:** `{{ $passcode }}`

@component('mail::button', ['url' => $loginUrl])
Open the Partner Portal
@endcomponent

For your security, keep this passcode private. We never display it anywhere else, so store it somewhere safe. If you ever lose it, reply to this email and we'll issue a new one — there's no self-service reset.

Thanks for partnering with us.

Best,

**Ahmad Baihaqie**<br>
Founder, Axel Nova Ventures<br>
baihaqie@axelnova.tech
@endcomponent
