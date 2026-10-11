<?php

namespace App\Support\Mail;

/**
 * Remembers which sending route (resend / hostinger) carried the message the
 * mailer just sent. Laravel's SentMessage doesn't say which failover member
 * delivered it, so each route's RouteTaggingTransport records itself here
 * and the email log pulls it straight after (MessageSent fires synchronously
 * after the transport returns).
 */
final class MailRouteRecorder
{
    private ?string $route = null;

    public function record(string $route): void
    {
        $this->route = $route;
    }

    /** The last recorded route, cleared so it can't leak into the next send. */
    public function pull(): ?string
    {
        [$route, $this->route] = [$this->route, null];

        return $route;
    }
}
