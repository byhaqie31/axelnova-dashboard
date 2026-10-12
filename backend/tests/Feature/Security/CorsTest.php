<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CORS must be an allowlist (frontend origins only), never `*` — a wildcard
 * would let any website script against the API with a victim's bearer token if
 * one ever leaked into a cookie/query. In production the API answers on the
 * admin host, so the public site's forms are cross-origin and must pass.
 */
class CorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_origin_gets_no_cors_grant(): void
    {
        $response = $this->getJson('/api/v1/services', ['Origin' => 'https://evil.example.com']);

        $this->assertNull(
            $response->headers->get('Access-Control-Allow-Origin'),
            'Unknown origins must not receive an Access-Control-Allow-Origin grant.',
        );
    }

    public function test_the_frontend_origin_passes_preflight(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/inquiries', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3003',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $this->assertSame(
            'http://localhost:3003',
            $response->headers->get('Access-Control-Allow-Origin'),
            'The frontend origin must be granted explicitly (not via wildcard).',
        );
    }

    public function test_the_public_site_can_post_the_quote_and_contact_forms(): void
    {
        // Both forms POST /v1/inquiries from axelnovaventures.com to the API on
        // the admin host: a non-credentialed, cross-origin request.
        $response = $this->call('OPTIONS', '/api/v1/inquiries', [], [], [], [
            'HTTP_ORIGIN' => 'https://axelnovaventures.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        $this->assertSame('https://axelnovaventures.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($response->headers->get('Access-Control-Allow-Credentials'), 'No cookies — the forms are stateless.');
    }

    public function test_the_public_site_can_use_an_access_link_without_cookies(): void
    {
        // /team/set-password and /partners/set-passcode are emailed on the public
        // site and call the API on the admin host. The grant is non-credentialed,
        // so the client (useApi) must send no cookies or the browser blocks it.
        $response = $this->call('OPTIONS', '/api/v1/auth/access-links/some-token', [], [], [], [
            'HTTP_ORIGIN' => 'https://axelnovaventures.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        $this->assertSame('https://axelnovaventures.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($response->headers->get('Access-Control-Allow-Credentials'));
    }
}
