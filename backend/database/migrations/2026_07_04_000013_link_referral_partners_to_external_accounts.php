<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 9 — link referral_partners to the new external_accounts identity and
 * migrate existing referrer credentials across.
 *
 * DATA MIGRATION (up):
 *   For every credentialed referrer (password NOT NULL, NOT soft-deleted) we
 *   mint an external_accounts row of type 'referrer', copying the already-hashed
 *   passcode VERBATIM (no re-hash), mapping status (active → active, else
 *   suspended), and carrying last_login_at. The referrer is then linked and its
 *   local password nulled — credentials now live solely on the account.
 *
 *   Referrers with a NULL password (never credentialed) get NO account here;
 *   staff mint one on approval / passcode reset (Admin\ReferralPartnersController).
 *
 *   Email collisions are impossible: referral_partners.email is UNIQUE, so no two
 *   referrers can share the email that becomes external_accounts.email (also UNIQUE).
 *
 * ROLLBACK (down):
 *   Copies the passcode + last_login_at back onto each linked referral_partner,
 *   drops the FK column. external_accounts / investors tables are dropped by their
 *   own migrations' downs. (Investor-typed accounts have no referrer to restore to
 *   and are simply discarded on their table drop.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_partners', function (Blueprint $table) {
            $table->foreignId('external_account_id')
                ->nullable()
                ->after('id')
                ->constrained('external_accounts')
                ->nullOnDelete();
        });

        // Only credentialed, live referrers get an account. Soft-deleted rows are
        // excluded (deleted_at IS NULL).
        $referrers = DB::table('referral_partners')
            ->whereNotNull('password')
            ->whereNull('deleted_at')
            ->get();

        foreach ($referrers as $referrer) {
            $accountId = DB::table('external_accounts')->insertGetId([
                'type' => 'referrer',
                'email' => $referrer->email,
                'password' => $referrer->password,           // hashed — copied verbatim
                'status' => $referrer->status === 'active' ? 'active' : 'suspended',
                'last_login_at' => $referrer->last_login_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('referral_partners')
                ->where('id', $referrer->id)
                ->update([
                    'external_account_id' => $accountId,
                    'password' => null,        // credentials now live on the account
                ]);
        }
    }

    public function down(): void
    {
        // Restore credentials onto referral_partners from their linked account so
        // the pre-Task-9 schema authenticates again.
        $linked = DB::table('referral_partners')
            ->whereNotNull('external_account_id')
            ->get();

        foreach ($linked as $referrer) {
            $account = DB::table('external_accounts')->where('id', $referrer->external_account_id)->first();
            if ($account) {
                DB::table('referral_partners')
                    ->where('id', $referrer->id)
                    ->update([
                        'password' => $account->password,
                        'last_login_at' => $account->last_login_at,
                    ]);
            }
        }

        Schema::table('referral_partners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('external_account_id');
        });
    }
};
