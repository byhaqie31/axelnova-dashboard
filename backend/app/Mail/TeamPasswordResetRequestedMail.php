<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Heads-up to the founder that a team member used "forgot password" on the
 * workspace sign-in. Unlike the partner flow there is NO auto-reset — team
 * passwords are only ever reset by the founder from the Users screen, so this
 * mail is the whole flow. Sent synchronously so it doesn't depend on the
 * queue worker.
 */
class TeamPasswordResetRequestedMail extends Mailable
{
    use SerializesModels;

    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Team password reset request — '.$this->user->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.team-password-reset-requested',
            with: ['user' => $this->user],
        );
    }
}
