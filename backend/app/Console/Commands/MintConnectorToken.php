<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Mints the scoped Sanctum token the MCP connector Worker authenticates with.
 *
 * The token belongs to the founder but carries ONLY the connector abilities
 * (connector:read + connector:draft) — never `cockpit` — so it can reach exactly
 * the /v1/connector/* surface and nothing else (the admin route group's
 * abilities:cockpit gate rejects it). Any prior token of the same name is revoked
 * first, so re-running rotates the credential cleanly.
 *
 * Run manually (never in CI/deploy). The plaintext token is shown ONCE; paste it
 * into the Worker secret:  cd connector && npx wrangler secret put CONNECTOR_TOKEN
 */
class MintConnectorToken extends Command
{
    /** Sanctum abilities the connector token is allowed to carry — and no more. */
    private const ABILITIES = ['connector:read', 'connector:draft'];

    /**
     * The token name; reused so a re-run revokes the previous one. Also the key
     * AppServiceProvider uses to exempt this token from the global Sanctum
     * expiration cap (its lifetime is the expires_at stamped below instead).
     */
    public const TOKEN_NAME = 'mcp-connector';

    protected $signature = 'connector:token
        {--email= : Founder email to mint for (defaults to the sole founder account)}
        {--days=30 : Token lifetime in days (sets its expires_at; the global Sanctum cap does not apply to this token)}';

    protected $description = 'Mint the scoped MCP-connector Sanctum token (connector:read + connector:draft) for the founder';

    public function handle(): int
    {
        $user = $this->resolveFounder();
        if (! $user instanceof User) {
            return self::FAILURE;
        }

        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('--days must be at least 1.');

            return self::FAILURE;
        }

        // Rotate: drop any existing token of this name so only one is ever live.
        $revoked = $user->tokens()->where('name', self::TOKEN_NAME)->delete();

        $expiresAt = now()->addDays($days);
        $token = $user->createToken(self::TOKEN_NAME, self::ABILITIES, $expiresAt)->plainTextToken;

        $this->newLine();
        $this->info("Minted '".self::TOKEN_NAME."' token for {$user->name} <{$user->email}>.");
        $this->line('  Abilities : '.implode(', ', self::ABILITIES));
        $this->line("  Expires   : {$expiresAt->toDayDateTimeString()} ({$days} days — re-run to rotate/extend)");
        if ($revoked > 0) {
            $this->line("  Rotated   : revoked {$revoked} prior '".self::TOKEN_NAME."' token(s).");
        }
        $this->newLine();
        $this->comment('Token (shown once — copy it now, it is not stored in plaintext):');
        $this->newLine();
        $this->line('    '.$token);
        $this->newLine();
        $this->comment('Next — store it as the Worker secret:');
        $this->line('    cd connector && npx wrangler secret put CONNECTOR_TOKEN   # paste the token above');
        $this->newLine();
        $this->warn('Note: this token is exempt from the global SANCTUM_EXPIRATION_MINUTES cap');
        $this->warn('(AppServiceProvider) — it lives until the expiry above. Re-run to rotate early.');

        return self::SUCCESS;
    }

    /** Resolve the founder to mint for, or emit an actionable error and return null. */
    private function resolveFounder(): ?User
    {
        $email = $this->option('email');

        if ($email) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $this->error("No user found with email '{$email}'.");

                return null;
            }
            if (! $user->isFounder()) {
                $this->error("{$user->email} is not a founder — the connector token must belong to the founder.");

                return null;
            }

            return $user;
        }

        $founders = User::whereIn('role', User::COCKPIT_ROLES)->get();

        if ($founders->isEmpty()) {
            $this->error('No founder account exists. Create one first, or pass --email=.');

            return null;
        }

        if ($founders->count() > 1) {
            $this->error('Multiple founder accounts exist — disambiguate with --email=<founder email>.');
            $this->line('  Founders: '.$founders->pluck('email')->implode(', '));

            return null;
        }

        return $founders->first();
    }
}
