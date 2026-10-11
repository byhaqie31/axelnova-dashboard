<?php

namespace App\Support\Mail;

use Carbon\CarbonInterface;

/**
 * Value formatting for emails, matching the owner's design kit
 * (docs/global/email-kit/): whole ringgit drop the cents, real cents stay,
 * dates read "10 Nov 2026".
 */
final class EmailFormat
{
    public static function money(float|int|string|null $value): string
    {
        $amount = round((float) $value, 2);

        return 'RM '.number_format($amount, fmod($amount, 1.0) === 0.0 ? 0 : 2);
    }

    /** "RM 5,000 to RM 8,000" — or a single figure when the range is closed. */
    public static function range(float|int|string|null $min, float|int|string|null $max): string
    {
        return round((float) $min, 2) === round((float) $max, 2)
            ? self::money($max)
            : self::money($min).' to '.self::money($max);
    }

    public static function date(?CarbonInterface $at): ?string
    {
        return $at?->format('j M Y');
    }

    public static function dateTime(?CarbonInterface $at): ?string
    {
        return $at?->format('j M Y, g:i a');
    }
}
