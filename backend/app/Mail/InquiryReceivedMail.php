<?php

namespace App\Mail;

use App\Models\Inquiry;
use App\Support\Mail\EmailFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Email 2 — the auto-reply to a contact / quote / partner / collaboration
 * form (kit: 02-inquiry-received.html is the contact variant). Each origin
 * keeps its own promise: a "tailored quote" only for project intent.
 */
class InquiryReceivedMail extends KitMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Inquiry $inquiry) {}

    public static function emailType(): string
    {
        return 'inquiry.received';
    }

    public static function relatedProperty(): ?string
    {
        return 'inquiry';
    }

    public function viewData(): array
    {
        $inquiry = $this->inquiry;
        $partner = $inquiry->origin === 'partner' ? $inquiry->collaborationPartner : null;

        $variant = match (true) {
            $partner !== null => 'partner',
            $inquiry->origin === 'collaboration' => 'collaboration',
            // Quote-copy ("tailored quote") only fits project intent — a contact
            // "General question" / "Feedback" gets a plain acknowledgement.
            $inquiry->origin === 'contact' => $inquiry->subject === 'Project inquiry' ? 'project' : 'contact',
            default => 'project',
        };

        return [
            'name' => $inquiry->name,
            'variant' => $variant,
            'partnerName' => $partner?->name,
            'partnerFounder' => $partner?->founder_name,
            'rows' => [
                ['Partner', $partner ? 'Axel Nova × '.$partner->name : null],
                ['Subject', $inquiry->subject],
                ['Project type', $inquiry->project_type],
                ['Budget', $inquiry->budget_hint],
                ['Timeline', $inquiry->timeline_hint],
                ['Received', EmailFormat::dateTime($inquiry->created_at)],
            ],
            'whatsappUrl' => filled(config('services.admin.whatsapp_url'))
                ? config('services.admin.whatsapp_url').'?text='.rawurlencode("Hi Qie, I'd like to chat about my project.")
                : null,
        ];
    }
}
