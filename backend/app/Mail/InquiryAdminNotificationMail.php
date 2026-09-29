<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the admin a new inquiry landed — from /quote or /contact alike. Reply-To
 * is the inquirer, so hitting reply in the inbox answers them directly.
 */
class InquiryAdminNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Inquiry $inquiry) {}

    public function envelope(): Envelope
    {
        $label = $this->inquiry->origin === 'contact'
            ? ($this->inquiry->subject ?: 'Contact message')
            : 'Project inquiry';

        return new Envelope(
            subject: "New inquiry: {$label} — {$this->inquiry->name}",
            replyTo: [new Address($this->inquiry->email, $this->inquiry->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.inquiry-admin-notification',
            with: [
                'inquiry' => $this->inquiry,
                'adminUrl' => rtrim((string) config('services.frontend.url'), '/').'/admin/inquiries/'.$this->inquiry->id,
            ],
        );
    }
}
