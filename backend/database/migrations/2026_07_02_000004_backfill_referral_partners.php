<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backfill: dedupe every existing referral's referrer (by email) into a
 * referral_partners row and repoint referrals.referral_partner_id. Existing
 * referrers are known/trusted, so they land as status = active — but with no
 * password, so they still can't log in until a marketer issues a passcode via
 * reset-passcode (no self-service). Denormalized referrer_* columns are kept.
 *
 * Uses the query builder (not Eloquent) so the migration is independent of the
 * model's later shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tiers = ['cold' => 5, 'warm' => 10, 'closed' => 15];

        $emails = DB::table('referrals')
            ->whereNotNull('referrer_email')
            ->whereNull('referral_partner_id')
            ->distinct()
            ->pluck('referrer_email');

        foreach ($emails as $email) {
            // Represent the referrer by their most recent referral (freshest contact
            // details + tier). A referrer already normalized in a prior run is reused.
            $latest = DB::table('referrals')
                ->where('referrer_email', $email)
                ->orderByDesc('id')
                ->first();

            if (! $latest) {
                continue;
            }

            $partnerId = DB::table('referral_partners')->where('email', $email)->value('id');

            if (! $partnerId) {
                $tier = $latest->relationship_tier ?: 'cold';

                $partnerId = DB::table('referral_partners')->insertGetId([
                    'code' => $this->uniqueCode(),
                    'name' => $latest->referrer_name,
                    'email' => $email,
                    'phone' => $latest->referrer_phone,
                    'relationship_tier' => $tier,
                    'commission_pct' => $tiers[$tier] ?? 5,
                    'agreed_terms' => $latest->agreed_terms ?? false,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('referrals')
                ->where('referrer_email', $email)
                ->update(['referral_partner_id' => $partnerId]);
        }
    }

    public function down(): void
    {
        // Detach referrals, then drop the rows this backfill created.
        DB::table('referrals')->update(['referral_partner_id' => null]);
        DB::table('referral_partners')->delete();
    }

    private function uniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (DB::table('referral_partners')->where('code', $code)->exists());

        return $code;
    }
};
