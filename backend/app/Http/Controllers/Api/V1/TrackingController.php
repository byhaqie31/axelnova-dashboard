<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PageView;
use App\Support\AnalyticsHash;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    /**
     * Record a public page view. Fire-and-forget from the client (beacon), so it
     * always returns 204 and never blocks. Raw IP is hashed, never stored. Obvious
     * bots are dropped so the counts reflect humans.
     */
    public function pageView(Request $request): Response
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:500'],
            'referrer' => ['nullable', 'string', 'max:500'],
        ]);

        $ua = (string) $request->userAgent();

        if (! self::isBot($ua)) {
            PageView::create([
                'path' => $data['path'],
                'ip_hash' => AnalyticsHash::forIp($request->ip()),
                'user_agent' => $ua !== '' ? mb_substr($ua, 0, 500) : null,
                'referrer' => $data['referrer'] ? mb_substr($data['referrer'], 0, 500) : null,
                'viewed_at' => now(),
            ]);
        }

        return response()->noContent();
    }

    private static function isBot(string $ua): bool
    {
        if ($ua === '') {
            return true;
        }

        return (bool) preg_match(
            '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegram|headless|lighthouse|pingdom|uptime|monitor|curl|wget|python-requests|axios|node-fetch/i',
            $ua,
        );
    }
}
