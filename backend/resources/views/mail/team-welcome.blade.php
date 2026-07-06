@component('mail::message')
# Welcome to the team, {{ $user->name }}!

I'm genuinely glad to have you with us at **Axel Nova Ventures**. This isn't just another account being switched on — it's the start of something we get to build together.

We're a small team with big intentions: to do work we're proud of, treat every client like they matter, and keep raising our own bar. There will be busy days and hard problems, but that's exactly where good teams are made. Bring your ideas, ask the bold questions, and don't be afraid to make things better than you found them.

Let's strive together, grow together, and turn every project into something we can point at and say — *we made that.*

Here's what you need to sign in:

- **Email:** {{ $user->email }}
- **Temporary password:** `{{ $password }}`

@component('mail::button', ['url' => $loginUrl])
Sign in to the Team Portal
@endcomponent

For your security, please change your password after your first sign-in and keep it private. This temporary password won't be shown again.

Welcome aboard — let's do great things.

**Ahmad Baihaqie**<br>
Founder, Axel Nova Ventures<br>
baihaqie@axelnova.tech
@endcomponent
