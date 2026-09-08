<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * New sign-in details for a teammate after the founder resets their password
 * from the Users screen (the only reset path for team accounts). Queued like
 * TeamWelcomeMail; the founder also sees the one-time password on-screen, so
 * this mail is a convenience channel rather than the sole copy. The plaintext
 * lives only in this in-flight message — the model stores only the hash.
 */
class TeamPasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $password,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Axel Nova Ventures team password has been reset',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.team-password-reset',
            with: [
                'user' => $this->user,
                'password' => $this->password,
                'loginUrl' => rtrim((string) config('services.frontend.public_url'), '/').'/team/login',
            ],
        );
    }
}
