@component('mail::message')
# Team password reset request

**{{ $user->name }}** used "forgot password" on the team workspace sign-in and is waiting on you — team passwords can only be reset by you.

- **Name:** {{ $user->name }}
- **Email:** {{ $user->email }}
- **Role:** {{ $user->role }}

Reset their password from the Users screen, then let them know their new credentials.

@component('mail::button', ['url' => rtrim(config('services.frontend.url'), '/').'/admin/users'])
Open Users
@endcomponent
@endcomponent
