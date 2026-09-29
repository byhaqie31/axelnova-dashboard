<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drops the Nuxt server's cached public pages (the `swr` route rules) so a
 * change shows on the very next visit instead of up to five minutes later.
 * Calls POST /_cache/purge on the frontend container (see
 * frontend/server/routes/_cache/purge.post.ts) with a shared secret.
 *
 * purge() only SCHEDULES the call: it runs once, after the response has been
 * sent (so the admin never waits on it), no matter how many changes the request
 * made. Unconfigured (dev, CI) → no-op. A failure is logged, never thrown — the
 * 5-minute swr window is the fallback.
 */
class SiteCache
{
    private bool $scheduled = false;

    public function purge(): void
    {
        if ($this->scheduled || ! $this->url() || ! $this->token()) {
            return;
        }

        $this->scheduled = true;

        dispatch(function () {
            $this->scheduled = false;
            $this->send();
        })->afterResponse();
    }

    private function send(): void
    {
        try {
            Http::timeout(3)->withToken($this->token())->post($this->url())->throw();
        } catch (Throwable $e) {
            Log::warning('Site cache purge failed — public pages refresh within 5 minutes instead.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function url(): ?string
    {
        return config('services.site_cache.purge_url') ?: null;
    }

    private function token(): ?string
    {
        return config('services.site_cache.purge_token') ?: null;
    }
}
