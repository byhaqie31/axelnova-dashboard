<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\ResolveCloudflareClientIp;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * The client IP must come from the real proxy chain, never from a header the
 * caller can write.
 *
 * Production is: visitor → Cloudflare → cloudflared (loopback) → host nginx →
 * this container. Host nginx appends the CF-verified client IP to
 * X-Forwarded-For, so the RIGHTMOST entry is the true client.
 *
 * This used to be configured as `trustProxies(at: '*')`, which makes Symfony
 * treat every hop as trusted and return the LEFTMOST entry — and Cloudflare
 * appends to whatever X-Forwarded-For the visitor sent, so that entry was
 * attacker-controlled. Every per-IP throttle (login, quotes, inquiries,
 * feedback) was bypassable by rotating one header. These tests pin the fix:
 * a forged prefix must never become the rate-limit key.
 */
class TrustedProxyTest extends TestCase
{
    /** Run a request through the real edge stack: CF resolution, then TrustProxies. */
    private function resolveIp(string $remoteAddr, ?string $forwardedFor = null, ?string $cfConnectingIp = null): string
    {
        $request = Request::create('/api/v1/services', 'GET', server: ['REMOTE_ADDR' => $remoteAddr]);

        if ($forwardedFor !== null) {
            $request->headers->set('X-Forwarded-For', $forwardedFor);
        }

        if ($cfConnectingIp !== null) {
            $request->headers->set('CF-Connecting-IP', $cfConnectingIp);
        }

        $resolved = '';
        (new ResolveCloudflareClientIp)->handle($request, function (Request $r) use (&$resolved) {
            return (new TrustProxies)->handle($r, function (Request $r2) use (&$resolved) {
                $resolved = (string) $r2->ip();

                return new Response;
            });
        });

        return $resolved;
    }

    public function test_a_forged_forwarded_prefix_does_not_become_the_client_ip(): void
    {
        // What an attacker sends: X-Forwarded-For: 9.9.9.9
        // Cloudflare appends the real client, host nginx appends it again.
        $ip = $this->resolveIp('172.20.0.1', '9.9.9.9, 203.0.113.7, 203.0.113.7');

        $this->assertSame(
            '203.0.113.7',
            $ip,
            'The forged leftmost X-Forwarded-For entry must be ignored — otherwise every per-IP throttle is bypassable.',
        );
    }

    public function test_the_real_client_ip_is_read_from_a_trusted_proxy_hop(): void
    {
        $this->assertSame('203.0.113.7', $this->resolveIp('172.20.0.1', '203.0.113.7'));
    }

    public function test_an_untrusted_source_cannot_forward_at_all(): void
    {
        // A public peer reaching the container directly (should be impossible —
        // the port binds to 127.0.0.1 — but never trust its headers regardless).
        $this->assertSame('198.51.100.5', $this->resolveIp('198.51.100.5', '9.9.9.9'));
    }

    public function test_a_request_with_no_forwarded_header_uses_the_socket_address(): void
    {
        $this->assertSame('172.20.0.1', $this->resolveIp('172.20.0.1'));
    }

    public function test_cloudflares_verified_ip_wins_over_a_forged_forwarded_chain(): void
    {
        // Cloudflare OVERWRITES CF-Connecting-IP, so it cannot be forged by the
        // visitor — it must beat anything sitting in X-Forwarded-For, whatever
        // the host nginx vhost happens to do with that header.
        $ip = $this->resolveIp('172.20.0.1', '9.9.9.9', '203.0.113.7');

        $this->assertSame('203.0.113.7', $ip);
    }

    public function test_cloudflare_header_is_ignored_from_an_untrusted_peer(): void
    {
        // A direct (non-proxied) caller must not be able to assert an identity.
        $this->assertSame('198.51.100.5', $this->resolveIp('198.51.100.5', null, '203.0.113.7'));
    }

    public function test_a_malformed_cloudflare_header_is_ignored(): void
    {
        $this->assertSame('203.0.113.7', $this->resolveIp('172.20.0.1', '203.0.113.7', 'not-an-ip'));
    }
}
