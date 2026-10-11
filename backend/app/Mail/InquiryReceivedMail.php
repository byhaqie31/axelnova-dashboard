<?php

namespace App\Mail;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InquiryReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Inquiry $inquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thanks for reaching out — Axel Nova Ventures',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.inquiry-received',
            with: [
                'inquiry' => $this->inquiry,
                // Quote-copy ("tailored quote") only fits project intent — a contact
                // "General question" / "Feedback" gets a plain acknowledgement, and a
                // studio applying to partner (origin 'collaboration') isn't a project.
                'isProject' => match ($this->inquiry->origin) {
                    'contact' => $this->inquiry->subject === 'Project inquiry',
                    'collaboration' => false,
                    default => true,
                },
                'partner' => $this->inquiry->origin === 'partner' ? $this->inquiry->collaborationPartner : null,
                'whatsappUrl' => config('services.admin.whatsapp_url')
                    .'?text='.rawurlencode("Hi Qie, I'd like to chat about my project."),
            ],
        );
    }
}
