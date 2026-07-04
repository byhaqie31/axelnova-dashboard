@component('mail::message')
# Partner passcode reset

**{{ $name !== '' ? $name : $account->email }}** used "forgot passcode" on the partner portal, so a new passcode has been automatically issued to their email.

- **Partner:** {{ $name !== '' ? $name : '—' }}
- **Account type:** {{ ucfirst($account->type) }}
- **Email:** {{ $account->email }}
@if ($account->isReferrer() && $account->referralCode())
- **Referral code:** {{ $account->referralCode() }}
@endif

No action needed — this is just a heads-up. If it looks suspicious, you can issue another passcode from the Referral Partners screen.
@endcomponent
