<?php

namespace App\Mail;

use App\Models\EmailLog;
use App\Support\Mail\EmailCatalog;
use App\Support\Mail\EmailFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;

/**
 * Email 28 — tells the owner an email to a client, partner or team member
 * bounced, was marked as spam, or failed to send (spec §5.6; kit:
 * 28-delivery-alert.html). Owner-audience itself, so it can never trigger
 * another alert.
 */
class EmailDeliveryAlertMail extends KitMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Related-record alias → [label, admin path]. */
    private const RECORDS = [
        'quotation' => ['Quotation', 'quotations'],
        'invoice' => ['Invoice', 'invoices'],
        'order' => ['Order', 'orders'],
        'payment' => ['Payment', 'payments'],
        'inquiry' => ['Inquiry', 'inquiries'],
        'feedback' => ['Feedback', 'feedback'],
        'referral' => ['Referral', 'referrals'],
        'user' => ['Team member', 'users'],
    ];

    public function __construct(public readonly EmailLog $log) {}

    public static function emailType(): string
    {
        return 'system.delivery_alert';
    }

    public static function relatedProperty(): ?string
    {
        return 'log';
    }

    public function viewData(): array
    {
        $log = $this->log;
        $emailLabel = EmailCatalog::has($log->type) ? EmailCatalog::label($log->type) : $log->type;
        $record = $log->relatedRecord();
        [$recordName, $adminPath] = self::RECORDS[$log->related_type] ?? [null, null];

        return [
            'emailLabel' => $emailLabel,
            'problem' => $log->status,
            'audience' => $log->audience === 'team' ? 'team member' : $log->audience,
            'recordLabel' => $record && $recordName ? $recordName.' '.$this->recordNumber($record) : $emailLabel,
            'recipient' => $log->to_email,
            'sentAt' => EmailFormat::dateTime($log->sent_at),
            'reason' => $log->status_detail,
            'recordUrl' => $record && $adminPath
                ? rtrim((string) config('services.frontend.url'), '/')."/admin/{$adminPath}/{$record->getKey()}"
                : null,
        ];
    }

    /** The human reference a record goes by (AXNI-…, AXNQ-…), else its id. */
    private function recordNumber(Model $record): string
    {
        foreach (['invoice_number', 'reference_code', 'order_number', 'receipt_number'] as $column) {
            if (filled($record->getAttribute($column))) {
                return (string) $record->getAttribute($column);
            }
        }

        return '#'.$record->getKey();
    }
}
