<?php

namespace App\Listeners\Mail;

use App\Services\Mail\EmailLogger;
use Illuminate\Mail\Events\MessageSent;
use Throwable;

/** A route accepted an email → one `sent` row. Never lets a logging error surface. */
final class RecordSentEmail
{
    public function __construct(private readonly EmailLogger $logger) {}

    public function handle(MessageSent $event): void
    {
        try {
            $this->logger->recordSent($event);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
