<?php

namespace App\Services\Mail;

use App\Mail\EmailDeliveryAlertMail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;

/**
 * The owner hears about an email that bounced, was marked as spam or
 * finally failed — once per email, and never for mail to their own inbox
 * (spec §5.6). `alerted_at` is claimed atomically, so two reports arriving
 * together still produce a single alert.
 */
class DeliveryAlerts
{
    public function maybeAlert(EmailLog $log): void
    {
        $adminEmail = (string) config('services.admin.email');

        if (! $log->isProblem() || $log->audience === 'admin' || $log->alerted_at !== null || $adminEmail === '') {
            return;
        }

        $claimed = EmailLog::whereKey($log->getKey())->whereNull('alerted_at')->update(['alerted_at' => now()]);
        if ($claimed !== 1) {
            return;
        }

        $log->refresh();
        Mail::to($adminEmail)->queue(new EmailDeliveryAlertMail($log));
    }
}
