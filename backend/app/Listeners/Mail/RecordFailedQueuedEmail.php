<?php

namespace App\Listeners\Mail;

use App\Services\Mail\EmailLogger;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobFailed;
use Throwable;

/**
 * A queued mailable (ShouldQueue → SendQueuedMailable) failed for the last
 * time → one `failed` row. JobFailed only fires once retries are exhausted,
 * so earlier attempts write nothing. Emails sent from inside our own jobs
 * are logged by those jobs' failed() hooks instead.
 */
final class RecordFailedQueuedEmail
{
    public function __construct(private readonly EmailLogger $logger) {}

    public function handle(JobFailed $event): void
    {
        try {
            $payload = $event->job->payload();
            if (($payload['data']['commandName'] ?? null) !== SendQueuedMailable::class) {
                return;
            }

            $command = unserialize($payload['data']['command']);
            if ($command instanceof SendQueuedMailable) {
                $this->logger->recordFailedMailable($command->mailable, $event->exception);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
