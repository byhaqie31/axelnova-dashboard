<?php

namespace App\Http\Resources;

use App\Support\Mail\EmailCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One row of a record's "Emails" history panel. */
class EmailLogResource extends JsonResource
{
    private const STATUS_LABELS = [
        'sent' => 'Sent',
        'delayed' => 'Delayed',
        'delivered' => 'Delivered',
        'bounced' => 'Bounced',
        'complained' => 'Spam complaint',
        'failed' => 'Failed',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => EmailCatalog::has($this->type) ? EmailCatalog::label($this->type) : $this->type,
            'audience' => $this->audience,
            'to_email' => $this->to_email,
            'to_name' => $this->to_name,
            'subject' => $this->subject,
            'status' => $this->status,
            'status_label' => self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status),
            'status_detail' => $this->status_detail,
            'route' => $this->route,
            'triggered_by' => $this->triggeredBy ? ['id' => $this->triggeredBy->id, 'name' => $this->triggeredBy->name] : null,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'status_changed_at' => $this->status_changed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Only Resend-routed rows exist in Resend's dashboard.
            'resend_url' => $this->route === 'resend' && $this->provider_id
                ? 'https://resend.com/emails/'.rawurlencode($this->provider_id)
                : null,
        ];
    }
}
