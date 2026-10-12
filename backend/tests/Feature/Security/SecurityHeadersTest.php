<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_responses_carry_baseline_security_headers(): void
    {
        $this->getJson('/api/v1/services')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_api_responses_are_kept_out_of_search(): void
    {
        // nginx serves /api on the public and the admin host alike — neither indexes it.
        $this->getJson('/api/v1/services')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
