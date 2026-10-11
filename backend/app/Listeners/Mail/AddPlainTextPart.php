<?php

namespace App\Listeners\Mail;

use App\Support\Mail\HtmlToText;
use Illuminate\Mail\Events\MessageSending;

/**
 * Emails are HTML views (the owner's design kit). Give each one a text part
 * derived from its HTML at send time, so it leaves as multipart/alternative —
 * HTML-only mail scores worse with spam filters.
 */
final class AddPlainTextPart
{
    public function handle(MessageSending $event): void
    {
        $email = $event->message;
        $html = $email->getHtmlBody();

        if ($email->getTextBody() === null && is_string($html) && $html !== '') {
            $email->text(HtmlToText::convert($html));
        }
    }
}
