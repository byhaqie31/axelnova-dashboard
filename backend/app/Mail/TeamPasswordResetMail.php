<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Email 21 — new sign-in details for a teammate after the founder resets their
 * password from the Users screen (the only reset path for team accounts).
 * Queued like TeamWelcomeMail; the founder also sees the one-time password
 * on-screen, so this mail is a convenience channel rather than the sole copy.
 * The plaintext lives only in this in-flight message — the model stores only
 * the hash.
 */
class TeamPasswordResetMail extends KitMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $password,
    ) {}

    public static function emailType(): string
    {
        return 'team.password_reset';
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
