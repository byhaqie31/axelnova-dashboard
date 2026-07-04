<?php

namespace Database\Factories;

use App\Models\ExternalAccount;
use App\Models\Referrer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Referrer>
 *
 * A bare factory row is the PROFILE only — no portal credentials (Task 9 moved
 * those to external_accounts). Chain ->credentialed() to also mint + link an
 * active account (passcode 12345678), mirroring an approved/migrated referrer.
 */
class ReferrerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'relationship_tier' => 'warm',
            'commission_pct' => 10,
            'agreed_terms' => true,
            'status' => 'active',
            'password' => null, // credentials live on the linked ExternalAccount
        ];
    }

    /** Mint + link an active portal account carrying the passcode 12345678. */
    public function credentialed(): static
    {
        return $this->afterCreating(function (Referrer $referrer) {
            $account = ExternalAccount::factory()->create(['email' => $referrer->email]);
            $referrer->forceFill(['external_account_id' => $account->id])->saveQuietly();
            $referrer->setRelation('account', $account);
        });
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function paused(): static
    {
        return $this->state(['status' => 'paused']);
    }
}
