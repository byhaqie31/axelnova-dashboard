<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Email 20 — warm welcome sent to a teammate the moment a founder provisions
 * them on the Users screen. Carries their one-time login details (email + the
 * generated password) alongside the welcome — the same credentials are shown
 * one-time in the founder's UI, so this mail is a convenience channel, not the
 * sole copy.
 *
 * Queued (ShouldQueue) like the other transactional mails: provisioning must
 * not hang or fail on SMTP, and the founder's on-screen copy is the fallback if
 * the worker is briefly down. The plaintext password lives only in this
 * in-flight message — it is never persisted (the model stores only the hash).
 */
class TeamWelcomeMail extends KitMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $password,
    ) {}

    public static function emailType(): string
    {
        return 'team.welcome';
    }

    public static function relatedProperty(): ?string
    {
        return 'user';
    }

    public function viewData(): array
    {
        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'password' => $this->password,
            'loginUrl' => rtrim((string) config('services.frontend.public_url'), '/').'/team/login',
        ];
    }
}
