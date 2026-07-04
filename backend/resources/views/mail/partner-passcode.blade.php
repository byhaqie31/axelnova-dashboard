@component('mail::message')
# Your Partner Portal access is ready

Hi {{ $name !== '' ? $name : 'there' }},

Your partner account with **Axel Nova Ventures** is ready. You can now sign in to the Partner Portal.

Use these details to log in:

- **Email:** {{ $account->email }}
- **Passcode:** `{{ $passcode }}`

@component('mail::button', ['url' => $loginUrl])
Open the Partner Portal
@endcomponent

For your security, keep this passcode private. We never display it anywhere else, so store it somewhere safe. If you ever lose it, use "Forgot passcode" on the login page and a fresh one will be emailed to you.

Thanks for partnering with us.

Best,

**Ahmad Baihaqie**<br>
Founder, Axel Nova Ventures<br>
baihaqie@axelnova.tech
@endcomponent
