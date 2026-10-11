<?php

namespace App\Services\Auth;

use App\Console\Commands\MintConnectorToken;
use App\Models\AccessToken;
use App\Models\ExternalAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * One-time links for setting or resetting a password (team, founder) or a
 * passcode (partners) — Phase 2 spec §3.1. No credential ever travels by
 * email: the email carries a link, the person chooses the secret.
 *
 * A link is single use, expires (set-up 72h, reset 60 min — config/auth.php
 * `access_links`), is stored only as a hash, and a newer link of the same
 * purpose replaces the account's earlier unused one. Redeeming signs the
 * account out everywhere.
 */
class AccessLinks
{
    /** Shortest secret each surface accepts. */
    private const MIN_LENGTH = ['team' => 12, 'admin' => 12, 'partner' => 10];

    /** Self-service "forgot" requests each address may make per hour, per surface. */
    private const FORGOT_PER_HOUR = 3;

    /**
     * Issue a link and return its URL. `$surface` picks the page it opens:
     * partners → the partner page, users → the team page unless 'admin'.
     */
    public function issue(Model $account, string $purpose, ?string $surface = null): string
    {
        if (! in_array($purpose, [AccessToken::SETUP, AccessToken::RESET], true)) {
            throw new InvalidArgumentException("Unknown access-link purpose [{$purpose}].");
        }

        $type = $this->accountType($account);
        $surface ??= $type === 'external_account' ? 'partner' : 'team';
        $token = Str::random(64);

        DB::transaction(function () use ($account, $type, $purpose, $surface, $token) {
            AccessToken::where('account_type', $type)
                ->where('account_id', $account->getKey())
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->delete();

            AccessToken::create([
                'account_type' => $type,
                'account_id' => $account->getKey(),
                'purpose' => $purpose,
                'surface' => $surface,
                'token_hash' => hash('sha256', $token),
                'expires_at' => $purpose === AccessToken::SETUP
                    ? now()->addHours((int) config('auth.access_links.setup_hours', 72))
                    : now()->addMinutes((int) config('auth.access_links.reset_minutes', 60)),
            ]);
        });

        return $this->url($surface, $token);
    }

    /** The live link for this token, or null when it's unknown, expired, used, or its account can't sign in. */
    public function inspect(string $token): ?AccessToken
    {
        if ($token === '') {
            return null;
        }

        $link = AccessToken::where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        return $link && $this->canSignIn($link) ? $link : null;
    }

    /**
     * Set the account's new password/passcode from a link: exactly once (the
     * link is claimed atomically), then every session of the account ends.
     */
    public function redeem(string $token, string $secret): Model
    {
        $link = $this->inspect($token) ?? throw new InvalidAccessLink;

        if (mb_strlen($secret) < $this->minimumLength($link->surface)) {
            throw new InvalidArgumentException('That is shorter than the minimum length.');
        }

        return DB::transaction(function () use ($link, $secret) {
            $claimed = AccessToken::whereKey($link->getKey())->whereNull('used_at')->update(['used_at' => now()]);
            if ($claimed !== 1) {
                throw new InvalidAccessLink;
            }

            $account = $link->account;
            $account->forceFill(['password' => $secret]); // hashed by the model cast
            if ($account instanceof User) {
                $account->forceFill(['password_set_at' => now()]);
            }
            $account->save();

            // Any other live link for this account (e.g. the welcome link after
            // a "forgot" reset) dies with this one — a set password is final.
            $this->revokeAll($account);

            // Signed out everywhere — except the founder's MCP connector token,
            // a machine credential minted from the VPS shell, not a session.
            $account->tokens()->where('name', '!=', MintConnectorToken::TOKEN_NAME)->delete();

            return $account;
        });
    }

    /** Kill every unused link of an account — on redeem, and when it's deactivated. */
    public function revokeAll(Model $account): void
    {
        AccessToken::where('account_type', $this->accountType($account))
            ->where('account_id', $account->getKey())
            ->whereNull('used_at')
            ->delete();
    }

    /**
     * Per-address brake for the self-service "forgot" endpoints, on top of the
     * per-IP login throttle: each request emails the person and replaces their
     * previous link, so without it anyone could flood an inbox or keep a link
     * stale. Counted whether or not the address matches an account, so the
     * response never differs. False → send nothing, answer as usual.
     */
    public function allowForgotRequest(string $surface, string $email): bool
    {
        $key = 'access-link-forgot:'.$surface.':'.sha1(mb_strtolower(trim($email)));

        if (RateLimiter::tooManyAttempts($key, self::FORGOT_PER_HOUR)) {
            return false;
        }

        RateLimiter::hit($key, 3600);

        return true;
    }

    public function minimumLength(string $surface): int
    {
        return self::MIN_LENGTH[$surface] ?? 12;
    }

    /** "72 hours" / "60 minutes" — for the emails. */
    public function lifetimeLabel(string $purpose): string
    {
        return $purpose === AccessToken::SETUP
            ? config('auth.access_links.setup_hours', 72).' hours'
            : config('auth.access_links.reset_minutes', 60).' minutes';
    }

    /** Where the person signs in once their link is used. */
    public function loginUrl(string $surface): string
    {
        return match ($surface) {
            'admin' => rtrim((string) config('services.frontend.url'), '/').'/admin/login',
            'partner' => rtrim((string) config('services.frontend.public_url'), '/').'/partners/login',
            default => rtrim((string) config('services.frontend.public_url'), '/').'/team/login',
        };
    }

    private function url(string $surface, string $token): string
    {
        return match ($surface) {
            'admin' => rtrim((string) config('services.frontend.url'), '/')."/admin/reset-password?token={$token}",
            'partner' => rtrim((string) config('services.frontend.public_url'), '/')."/partners/set-passcode?token={$token}",
            default => rtrim((string) config('services.frontend.public_url'), '/')."/team/set-password?token={$token}",
        };
    }

    private function accountType(Model $account): string
    {
        return match (true) {
            $account instanceof User => 'user',
            $account instanceof ExternalAccount => 'external_account',
            default => throw new InvalidArgumentException('Access links are for users and partner accounts only.'),
        };
    }

    private function canSignIn(AccessToken $link): bool
    {
        $account = $link->account;

        return match (true) {
            $account instanceof User => ! $account->isDeactivated()
                && ($link->surface !== 'admin' || $account->isCockpit()),
            $account instanceof ExternalAccount => $account->isActive(),
            default => false,
        };
    }
}
