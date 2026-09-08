@component('mail::message')
# Your password has been reset, {{ $user->name }}

A new temporary password has been issued for your Axel Nova Ventures team account. Any sessions that were still signed in have been closed.

Here's what you need to sign in:

- **Email:** {{ $user->email }}
- **Temporary password:** `{{ $password }}`

@component('mail::button', ['url' => $loginUrl])
Sign in to the Team Portal
@endcomponent

If you didn't ask for this reset, reply to this email straight away so we can look into it.

**Ahmad Baihaqie**<br>
Founder, Axel Nova Ventures<br>
baihaqie@axelnova.tech
@endcomponent
