<?php

namespace App\Services\Mail;

/**
 * Verifies a Resend webhook (Resend signs with Svix): the signed content is
 * "{svix-id}.{svix-timestamp}.{raw body}", HMAC-SHA256 with the base64 key
 * after the "whsec_" prefix; `svix-signature` carries one or more
 * space-separated "v1,<base64>" entries. Timestamps more than five minutes
 * off are rejected, so a captured request can't be replayed later.
 */
final class ResendSignature
{
    private const TOLERANCE_SECONDS = 300;

    public static function verify(
        string $id,
        string $timestamp,
        string $signatureHeader,
        string $body,
        string $secret,
        ?int $now = null,
    ): bool {
        if ($id === '' || ! ctype_digit($timestamp) || $signatureHeader === '' || ! str_starts_with($secret, 'whsec_')) {
            return false;
        }

        if (abs(($now ?? now()->getTimestamp()) - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $key = base64_decode(substr($secret, 6), true);
        if ($key === false) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key, true));

        foreach (explode(' ', $signatureHeader) as $entry) {
            [$version, $signature] = array_pad(explode(',', $entry, 2), 2, '');
            if ($version === 'v1' && hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
