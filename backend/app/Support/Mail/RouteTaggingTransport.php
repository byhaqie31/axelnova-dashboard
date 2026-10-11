<?php

namespace App\Support\Mail;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Wraps one sending route (the `tagged` mail transport, config/mail.php) and
 * records its name once a send succeeds — so behind the failover mailer the
 * email log knows whether Resend or the Hostinger backup carried a message.
 * A failed send records nothing and rethrows, letting failover move on.
 */
final class RouteTaggingTransport implements TransportInterface
{
    public function __construct(
        private readonly TransportInterface $inner,
        private readonly string $route,
        private readonly MailRouteRecorder $recorder,
    ) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $sent = $this->inner->send($message, $envelope);
        $this->recorder->record($this->route);

        return $sent;
    }

    public function __toString(): string
    {
        return "tagged({$this->route}):".$this->inner;
    }
}
