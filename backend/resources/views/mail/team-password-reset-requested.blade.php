@component('mail::message')
# Team password reset request

**{{ $user->name }}** used "forgot password" on the team workspace sign-in and is waiting on you — team passwords can only be reset by you.

- **Name:** {{ $user->name }}
- **Email:** {{ $user->email }}
- **Role:** {{ $user->role }}

Open the Users screen and use **Reset password** on their row — a new temporary password is emailed to them and shown to you once.

@component('mail::button', ['url' => rtrim(config('services.frontend.url'), '/').'/admin/users'])
Open Users
@endcomponent
@endcomponent
