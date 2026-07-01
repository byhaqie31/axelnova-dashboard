@component('mail::message')
# Partner passcode reset

**{{ $referrer->name }}** used "forgot passcode" on the partner portal, so a new passcode has been automatically issued to their email.

- **Partner:** {{ $referrer->name }}
- **Email:** {{ $referrer->email }}
- **Referral code:** {{ $referrer->code }}

No action needed — this is just a heads-up. If it looks suspicious, you can issue another passcode from the Referral Partners screen.
@endcomponent
