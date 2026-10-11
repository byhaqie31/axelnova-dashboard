<?php

namespace App\Listeners\Mail;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Str;
use Throwable;

/**
 * Give every email a Message-ID we choose, before it reaches the transport —
 * the email log stores it, and Resend's delivery reports echo it back as
 * `data.message_id`, which is how a report finds its row (spec §5.5).
 */
final class StampMessageId
{
    /** Sender domain (spec decision 1 — axelnova.tech). */
    private const DOMAIN = 'axelnova.tech';

    public function handle(MessageSending $event): void
    {
        try {
            $headers = $event->message->getHeaders();

            if (! $headers->has('Message-ID')) {
                $headers->addIdHeader('Message-ID', Str::uuid()->toString().'@'.self::DOMAIN);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
