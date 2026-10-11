<?php

namespace App\Services\Mail;

use App\Models\EmailLog;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Finds the email_logs row a Resend report is about (spec §5.5), in order:
 *   1. data.message_id — the Message-ID we stamped on the email;
 *   2. data.email_id   — Resend's own id, once stored on the row;
 *   3. same recipient + subject, sent within 10 minutes of the email's
 *      creation — the most recent row not yet tied to a Resend id.
 */
final class ResendEventMatcher
{
    private const WINDOW_MINUTES = 10;

    /** @param  array<string, mixed>  $data  the webhook's `data` object */
    public function match(array $data): ?EmailLog
    {
        $messageId = trim((string) ($data['message_id'] ?? ''), "<> \t");
        if ($messageId !== '' && ($log = EmailLog::where('message_id', $messageId)->first())) {
            return $log;
        }

        $emailId = (string) ($data['email_id'] ?? '');
        if ($emailId !== '' && ($log = EmailLog::where('provider_id', $emailId)->first())) {
            return $log;
        }

        $to = is_array($data['to'] ?? null) ? ($data['to'][0] ?? null) : ($data['to'] ?? null);
        $subject = $data['subject'] ?? null;
        $createdAt = $this->time($data['created_at'] ?? null);

        if (! is_string($to) || ! is_string($subject) || $createdAt === null) {
            return null;
        }

        return EmailLog::where('to_email', $to)
            ->where('subject', $subject)
            ->whereNull('provider_id')
            ->whereBetween('sent_at', [
                $createdAt->copy()->subMinutes(self::WINDOW_MINUTES),
                $createdAt->copy()->addMinutes(self::WINDOW_MINUTES),
            ])
            ->latest('sent_at')
            ->first();
    }

    /**
     * Resend sends UTC (`…Z` / `+00:00`); `sent_at` is stored as app-timezone
     * wall-clock time (Asia/Kuala_Lumpur) and query bindings are formatted
     * without an offset — so convert before comparing, or the window misses.
     */
    private function time(mixed $value): ?Carbon
    {
        try {
            return is_string($value) && $value !== ''
                ? Carbon::parse($value)->setTimezone((string) config('app.timezone'))
                : null;
        } catch (Throwable) {
            return null;
        }
    }
}
