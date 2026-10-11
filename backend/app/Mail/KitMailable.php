<?php

namespace App\Mail;

use App\Mail\Contracts\LoggableEmail;
use App\Support\Mail\EmailCatalog;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An email built from the owner's design kit. The subclass names its catalog
 * type and maps its models to presentation-ready template data; the catalog
 * supplies the template and the subject line (EmailCatalog), so every email
 * of a type — real or `mail:preview` — renders and is titled the same way.
 */
abstract class KitMailable extends Mailable implements LoggableEmail
{
    /** The email's key in EmailCatalog, e.g. "invoice.sent". */
    abstract public static function emailType(): string;

    /** The public property holding the record this email is about (for the email log). */
    abstract public static function relatedProperty(): ?string;

    /** @return array<string, mixed> presentation-ready values for the template */
    abstract public function viewData(): array;

    public function envelope(): Envelope
    {
        return new Envelope(subject: EmailCatalog::subject(static::emailType(), $this->viewData()));
    }

    public function content(): Content
    {
        return new Content(view: EmailCatalog::view(static::emailType()), with: $this->viewData());
    }

    /**
     * Laravel merges a mailable's public properties OVER its `with` data, so a
     * constructor property sharing a template key (ReferralCommissionMail's
     * float $commission vs the formatted "RM 650") would silently win. The
     * template contract is viewData() — make it win. (Laravel still injects
     * its own `$message` last, so no template key may be named "message".)
     */
    public function buildViewData()
    {
        return array_merge(parent::buildViewData(), $this->viewData());
    }
}
