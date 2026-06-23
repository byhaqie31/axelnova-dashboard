<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give orders a single agreed total and payment tracking, replacing the
     * estimate range as the headline figure. The min/max columns stay for
     * historical reference but are no longer surfaced in the UI.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('final_amount_myr', 12, 2)->default(0)->after('value_max_myr');
            $table->unsignedTinyInteger('deposit_pct')->nullable()->after('final_amount_myr');
            $table->decimal('amount_paid_myr', 12, 2)->default(0)->after('deposit_pct');
        });

        // Backfill existing orders: the max of the old range is the best
        // available stand-in for the agreed total; nothing collected yet.
        DB::table('orders')->update([
            'final_amount_myr' => DB::raw('value_max_myr'),
            'deposit_pct' => 50,
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['final_amount_myr', 'deposit_pct', 'amount_paid_myr']);
        });
    }
};
