<?php

namespace App\Services\Mail;

use App\Mail\Contracts\LoggableEmail;
use App\Models\EmailLog;
use App\Support\Mail\EmailCatalog;
use App\Support\Mail\MailRouteRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\IdentificationHeader;
use Throwable;

/**
 * Writes the email log (spec §5.4): one `sent` row when a route accepts an
 * email, one `failed` row when a send fails for the last time. Classifies
 * each email from its mailable (LoggableEmail: catalog type + the property
 * holding its record). Callers wrap this so logging can never block a send.
 */
class EmailLogger
{
    public function __construct(private readonly MailRouteRecorder $routes) {}

    public function recordSent(MessageSent $event): ?EmailLog
    {
        $route = $this->routes->pull() ?? (string) config('mail.default');
        $email = $event->sent->getOriginalMessage();
        $to = $email instanceof Email ? ($email->getTo()[0] ?? null) : null;

        if ($to === null) {
            return null;
        }

        $mailable = $event->data['__laravel_mailable'] ?? null;
        $prop = is_string($mailable) && is_subclass_of($mailable, LoggableEmail::class) ? $mailable::relatedProperty() : null;
        $related = $prop !== null ? ($event->data[$prop] ?? null) : null;

        $header = $email->getHeaders()->get('Message-ID');
        $messageId = $header instanceof IdentificationHeader ? $header->getId() : null;
        $providerId = $event->sent->getMessageId();

        return EmailLog::create($this->classify($mailable, $to->getAddress(), $related) + [
            'to_email' => $to->getAddress(),
            'to_name' => $to->getName() ?: null,
            'subject' => $email->getSubject(),
            'route' => $route,
            'status' => 'sent',
            'message_id' => $messageId,
            // Resend may answer the SMTP DATA with its own id (a UUID). Other servers'
            // replies parse into junk ids (Mailtrap/Postfix "Ok: queued" → "queued").
            'provider_id' => $route === 'resend' && Str::isUuid($providerId) ? $providerId : null,
            'triggered_by' => $this->actor(),
            'sent_at' => now(),
            'status_changed_at' => now(),
        ]);
    }

    /** A queued mailable (SendQueuedMailable) failed for the last time. */
    public function recordFailedMailable(Mailable $mailable, Throwable $e): ?EmailLog
    {
        $to = $mailable->to[0] ?? null;
        if (! is_array($to) || empty($to['address'])) {
            return null;
        }

        $prop = $mailable instanceof LoggableEmail ? $mailable::relatedProperty() : null;
        $related = $prop !== null && isset($mailable->{$prop}) ? $mailable->{$prop} : null;

        try {
            $subject = method_exists($mailable, 'envelope') ? $mailable->envelope()->subject : $mailable->subject;
        } catch (Throwable) {
            $subject = null;
        }

        return $this->recordFailure($mailable::class, $related, $to['address'], $to['name'] ?? null, $subject, $e);
    }

    /** A send failed for the last time — no route accepted it. */
    public function recordFailure(string $mailableClass, ?Model $related, string $toEmail, ?string $toName, ?string $subject, Throwable $e): EmailLog
    {
        $log = EmailLog::create($this->classify($mailableClass, $toEmail, $related) + [
            'to_email' => $toEmail,
            'to_name' => $toName ?: null,
            'subject' => $subject,
            'route' => null,
            'status' => 'failed',
            'status_detail' => Str::limit($e->getMessage(), 1000),
            'triggered_by' => $this->actor(),
            'sent_at' => null,
            'status_changed_at' => now(),
        ]);

        app(DeliveryAlerts::class)->maybeAlert($log);

        return $log;
    }

    /** @return array{type: string, audience: string, related_type: ?string, related_id: ?int} */
    private function classify(?string $mailableClass, string $toEmail, mixed $related): array
    {
        $type = is_string($mailableClass) && is_subclass_of($mailableClass, LoggableEmail::class)
            ? $mailableClass::emailType()
            : ($mailableClass ? class_basename($mailableClass) : 'unknown');

        $audience = EmailCatalog::has($type)
            ? EmailCatalog::audience($type)
            // Unclassified mail: the owner's own inbox is admin, anyone else a client.
            : (strcasecmp($toEmail, (string) config('services.admin.email')) === 0 ? 'admin' : 'client');

        $isRecord = $related instanceof Model && $related->getKey() !== null;

        return [
            'type' => $type,
            'audience' => $audience,
            'related_type' => $isRecord ? EmailLog::aliasFor($related) : null,
            'related_id' => $isRecord ? (int) $related->getKey() : null,
        ];
    }

    /** The admin/team user whose request led to this send (carried into queued jobs by Context). */
    private function actor(): ?int
    {
        $id = Context::getHidden('mail_actor_id');

        return is_numeric($id) ? (int) $id : null;
    }
}
