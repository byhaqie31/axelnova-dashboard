<?php

namespace App\Mail;

use App\Models\Inquiry;
use App\Support\Mail\EmailFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email 25 — tells the owner a new inquiry landed — from /quote, /contact or
 * the /collaborations pages alike. Reply-To is the inquirer, so hitting reply
 * in the inbox answers them directly.
 */
class InquiryAdminNotificationMail extends KitMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Inquiry $inquiry) {}

    public static function emailType(): string
    {
        return 'inquiry.new_alert';
    }

    public static function relatedProperty(): ?string
    {
        return 'inquiry';
    }

    public function envelope(): Envelope
    {
        return parent::envelope()->replyTo(new Address($this->inquiry->email, $this->inquiry->name));
    }

    public function viewData(): array
    {
        $inquiry = $this->inquiry;
        $partner = $inquiry->collaborationPartner;

        return [
            'origin' => $inquiry->origin,
            'subjectLabel' => match ($inquiry->origin) {
                'contact' => $inquiry->subject ?: 'Contact message',
                'partner' => 'Axel Nova × '.($partner?->name ?? 'partner studio'),
                'collaboration' => 'Partnership application',
                default => 'Project inquiry',
            },
            'name' => $inquiry->name,
            'partnerName' => $partner?->name ?? 'a partner studio',
            'rows' => [
                ['Partner', $partner?->name],
                ['Subject', $inquiry->subject],
                ['Name', $inquiry->name],
                ['Email', $inquiry->email],
                ['Phone', $inquiry->phone],
                ['Company', $inquiry->company],
                ['Project type', $inquiry->project_type],
                ['Budget', $inquiry->budget_hint],
                ['Timeline', $inquiry->timeline_hint],
                ['Source', $inquiry->source === 'referral' ? 'Referral' : null],
                ['Submitted', EmailFormat::dateTime($inquiry->created_at)],
            ],
            'inquiryMessage' => (string) $inquiry->message,
            'adminUrl' => rtrim((string) config('services.frontend.url'), '/').$inquiry->adminPath(),
        ];
    }
}
