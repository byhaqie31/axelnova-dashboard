<?php

namespace App\Support;

/**
 * One-way, stable hash of a visitor IP for analytics. We never store raw IPs —
 * only sha256(ip + app secret), which is enough to (a) count unique visitors and
 * (b) dedupe likes (one per entity per ip), without being reversible. The salt is
 * the app key so the hash is stable over time (required for the like uniqueness
 * constraint) but useless outside this app.
 */
class AnalyticsHash
{
    public static function forIp(?string $ip): string
    {
        return hash('sha256', ($ip ?: '0.0.0.0').'|'.config('app.key'));
    }
}
