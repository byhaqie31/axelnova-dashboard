<?php

namespace Tests\Feature\Mail;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * HTML-only views get a generated text part at send time, so every email
 * leaves as multipart/alternative.
 */
class PlainTextPartTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_only_mail_is_sent_with_a_generated_text_part(): void
    {
        config(['mail.default' => 'array']);

        Mail::to('client@example.com')->send(new class extends Mailable
        {
            public function envelope(): Envelope
            {
                return new Envelope(subject: 'Plain text check');
            }

            public function content(): Content
            {
                return new Content(htmlString: '<html><body><h1>Hello there</h1><p>Body copy</p></body></html>');
            }
        });

        $sent = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

        $this->assertStringContainsString('Hello there', (string) $sent->getTextBody());
        $this->assertStringContainsString('Body copy', (string) $sent->getTextBody());
    }
}
