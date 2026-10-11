<?php

namespace App\Mail;

use App\Support\Mail\EmailCatalog;
use App\Support\Mail\EmailSamples;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Any catalog template filled with its sample data — `php artisan mail:preview`
 * sends these to the dev mailer (Mailtrap) so the owner can check every email
 * at desktop width and at 390px without triggering the real flows.
 */
class PreviewMail extends Mailable
{
    public function __construct(public readonly string $type) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Preview] '.EmailCatalog::subject($this->type, EmailSamples::for($this->type)),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: EmailCatalog::view($this->type),
            with: EmailSamples::for($this->type),
        );
    }
}
