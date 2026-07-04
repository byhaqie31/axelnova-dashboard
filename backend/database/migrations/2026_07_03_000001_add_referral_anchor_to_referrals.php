<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('referral_partner_id')
                ->constrained('quotations')->nullOnDelete();
            $table->unsignedTinyInteger('commission_pct')->nullable()->after('commission_tier_pct');
        });
        DB::statement("ALTER TABLE referrals MODIFY status ENUM('new','contacted','qualified','draft','converted','rejected') NOT NULL DEFAULT 'new'");
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->dropColumn(['quotation_id', 'commission_pct']);
        });
        DB::statement("ALTER TABLE referrals MODIFY status ENUM('new','contacted','qualified','converted','rejected') NOT NULL DEFAULT 'new'");
    }
};
