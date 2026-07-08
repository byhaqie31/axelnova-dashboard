<?php

namespace Tests\Feature\Connector;

use App\Models\PricingConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Lifetime rules for the Worker's `mcp-connector` Sanctum token.
 *
 * The global SANCTUM_EXPIRATION_MINUTES cap (12h default, Phase 0) exists so a
 * leaked admin *login* token can't live forever — but it must not kill the
 * connector credential, which is long-lived by design and bounded by its OWN
 * `expires_at` (minted via `connector:token --days=N`). The exemption is
 * narrow: only the token named `mcp-connector`, and only when it carries an
 * explicit, still-future expiry.
 */
class ConnectorTokenLifetimeTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        // Minimal active pricing config so /catalog renders (empty catalog is fine).
        PricingConfig::factory()->create([
            'config' => [
                'currency' => 'MYR',
                'valid_for_days' => 30,
                'rush_multiplier' => 1.20,
                'base_packages' => [],
                'modifiers' => [],
                'addons' => [],
            ],
        ]);

        $this->founder = User::factory()->founder()->create();

        // Pin the global cap the exemption must survive (the repo default).
        config(['sanctum.expiration' => 720]);
    }

    /**
     * Mint a token, then age its created_at past the global cap.
     *
     * @param  list<string>  $abilities
     */
    private function agedTokenHeader(string $name, array $abilities, ?\DateTimeInterface $expiresAt): array
    {
        $plain = $this->founder->createToken($name, $abilities, $expiresAt)->plainTextToken;
        $this->founder->tokens()->where('name', $name)->update(['created_at' => now()->subDays(2)]);

        return ['Authorization' => "Bearer {$plain}"];
    }

    public function test_connector_token_outlives_the_global_expiration_cap(): void
    {
        $headers = $this->agedTokenHeader(
            'mcp-connector',
            ['connector:read', 'connector:draft'],
            now()->addDays(30),
        );

        $this->getJson('/api/v1/connector/catalog', $headers)->assertOk();
    }

    public function test_connector_token_past_its_own_expires_at_is_rejected(): void
    {
        $plain = $this->founder->createToken(
            'mcp-connector',
            ['connector:read', 'connector:draft'],
            now()->subHour(),
        )->plainTextToken;

        $this->getJson('/api/v1/connector/catalog', ['Authorization' => "Bearer {$plain}"])
            ->assertUnauthorized();
    }

    public function test_connector_token_without_an_expiry_still_falls_under_the_global_cap(): void
    {
        $headers = $this->agedTokenHeader(
            'mcp-connector',
            ['connector:read', 'connector:draft'],
            null,
        );

        $this->getJson('/api/v1/connector/catalog', $headers)->assertUnauthorized();
    }

    public function test_other_tokens_keep_the_global_expiration_cap(): void
    {
        // Same abilities, different name — the exemption must be name-scoped.
        $headers = $this->agedTokenHeader(
            'admin-login',
            ['connector:read', 'connector:draft'],
            now()->addDays(30),
        );

        $this->getJson('/api/v1/connector/catalog', $headers)->assertUnauthorized();
    }

    public function test_mint_command_stamps_the_default_thirty_day_expiry(): void
    {
        $this->artisan('connector:token')->assertExitCode(0);

        $token = $this->founder->tokens()->where('name', 'mcp-connector')->sole();
        $this->assertNotNull($token->expires_at);
        $this->assertEqualsWithDelta(
            now()->addDays(30)->timestamp,
            $token->expires_at->timestamp,
            5,
        );
    }

    public function test_mint_command_honours_a_custom_days_option(): void
    {
        $this->artisan('connector:token', ['--days' => 90])->assertExitCode(0);

        $token = $this->founder->tokens()->where('name', 'mcp-connector')->sole();
        $this->assertEqualsWithDelta(
            now()->addDays(90)->timestamp,
            $token->expires_at->timestamp,
            5,
        );
    }

    public function test_mint_command_plain_output_is_exactly_one_working_token(): void
    {
        // --plain exists for the rotate-token script: stdout must be the raw
        // token and nothing else, so it can be piped into `wrangler secret put`.
        $this->withoutMockingConsoleOutput();
        $exit = Artisan::call('connector:token', ['--plain' => true]);
        $output = trim(Artisan::output());

        $this->assertSame(0, $exit);
        $this->assertCount(1, explode("\n", $output), 'plain output must be a single line');

        $this->getJson('/api/v1/connector/catalog', ['Authorization' => "Bearer {$output}"])
            ->assertOk();
    }
}
