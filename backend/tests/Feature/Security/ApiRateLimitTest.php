<?php

namespace Tests\Feature\Security;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Every /api route must sit under a rate-limit floor. Throttling used to be
 * opt-in per route group, so the public token lookups (/v1/documents/{token},
 * /v1/feedback/{token}) and the authenticated writes on /v1/partner, /v1/team
 * and /v1/admin had no ceiling at all — a free unauthenticated loop against
 * the database. The tight per-route throttles still run on top of this.
 */
class ApiRateLimitTest extends TestCase
{
    private function limitFor(string $ip): Limit
    {
        $limiter = RateLimiter::limiter('api');

        $this->assertNotNull($limiter, 'The global `api` rate limiter must be registered.');

        $request = Request::create('/api/v1/services', 'GET', server: ['REMOTE_ADDR' => $ip]);

        return $limiter($request);
    }

    public function test_public_traffic_is_capped_per_ip(): void
    {
        $limit = $this->limitFor('203.0.113.7');

        $this->assertSame(300, $limit->maxAttempts);
        $this->assertSame('api:ip:203.0.113.7', $limit->key);
    }

    public function test_two_public_ips_get_separate_buckets(): void
    {
        $this->assertNotSame(
            $this->limitFor('203.0.113.7')->key,
            $this->limitFor('198.51.100.5')->key,
            'One visitor must never be able to exhaust another visitor\'s allowance.',
        );
    }

    public function test_internal_ssr_traffic_gets_a_higher_shared_ceiling(): void
    {
        // The Nuxt SSR server calls the API from inside the docker network, so
        // every visitor to a server-rendered page shares this one source IP.
        // It still gets a ceiling — just not a per-visitor one.
        $limit = $this->limitFor('172.20.0.4');

        $this->assertSame(3000, $limit->maxAttempts);
        $this->assertSame('api:internal:172.20.0.4', $limit->key);
    }

    public function test_the_floor_is_applied_to_api_responses(): void
    {
        $this->getJson('/api/v1/services')
            ->assertHeader('X-RateLimit-Limit');
    }
}
