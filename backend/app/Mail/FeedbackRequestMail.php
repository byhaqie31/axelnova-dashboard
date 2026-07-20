<?php

namespace App\Mail;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedbackRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Feedback $feedback) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'How did we do? — quick feedback on your Axel Nova project',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.feedback-request',
            with: [
                'feedback' => $this->feedback,
                'feedbackUrl' => rtrim((string) config('services.frontend.public_url'), '/')
                    ."/feedback/{$this->feedback->public_token}",
            ],
        );
    }
}
