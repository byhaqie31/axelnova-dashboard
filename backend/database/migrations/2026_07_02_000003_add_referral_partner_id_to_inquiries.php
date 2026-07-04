<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attribute an inquiry to the referrer whose ?ref link brought it in (resolved from
 * the axn_ref cookie / ?ref param at submit time). Null = public/organic. Feeds the
 * Phase 1 collected-revenue attribution query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->foreignId('referral_partner_id')
                ->nullable()
                ->after('client_id')
                ->constrained('referral_partners')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropForeign(['referral_partner_id']);
            $table->dropColumn('referral_partner_id');
        });
    }
};
