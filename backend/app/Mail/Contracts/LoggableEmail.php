<?php

namespace App\Mail\Contracts;

/**
 * An email the email log can classify and link (spec §5.3). The log listener
 * sees only the mailable's class and its view data, so both answers are
 * static: the catalog type, and the name of the public property that holds
 * the record the email is about (null when it has none).
 */
interface LoggableEmail
{
    /** The email's key in App\Support\Mail\EmailCatalog. */
    public static function emailType(): string;

    /** Name of the public property holding the related model, or null. */
    public static function relatedProperty(): ?string;
}
