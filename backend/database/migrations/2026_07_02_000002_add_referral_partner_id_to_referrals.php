<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point each referral at its normalized referrer. Nullable + nullOnDelete so the
 * denormalized referrer_* columns still stand during the transition; the backfill
 * migration repoints existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->foreignId('referral_partner_id')
                ->nullable()
                ->after('id')
                ->constrained('referral_partners')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['referral_partner_id']);
            $table->dropColumn('referral_partner_id');
        });
    }
};
