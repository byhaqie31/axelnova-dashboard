<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API errors must render as JSON even without an Accept header — otherwise an
 * unauthenticated hit throws "Route [login] not defined" (there is no web
 * login) and surfaces as a 500 instead of a clean 401.
 */
class ApiJsonErrorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_requests_get_a_json_401_without_an_accept_header(): void
    {
        $response = $this->post('/api/v1/admin/team-session');

        $response->assertUnauthorized();
        $this->assertJson($response->content());
    }
}
