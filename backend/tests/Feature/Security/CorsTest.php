<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CORS must be an allowlist (frontend origins only), never `*`. Production is
 * same-origin behind nginx, so this mostly guards dev and any future
 * split-origin topology — but a wildcard would let any website script against
 * the API with a victim's bearer token if one ever leaked into a cookie/query.
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
        $response = $this->call('OPTIONS', '/api/v1/quote-requests', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3003',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $this->assertSame(
            'http://localhost:3003',
            $response->headers->get('Access-Control-Allow-Origin'),
            'The frontend origin must be granted explicitly (not via wildcard).',
        );
    }
}
