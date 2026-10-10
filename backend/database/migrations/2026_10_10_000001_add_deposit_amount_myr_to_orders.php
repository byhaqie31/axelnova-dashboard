<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A quotation may agree a FIXED deposit (e.g. RM 2,700 on RM 14,340) rather
     * than a whole-number percentage. Carry that amount onto the order so
     * Order::deposit_due_myr is the agreed figure, never a recomputed pct
     * (19% → RM 2,724.60). Nullable; pct-deposit orders keep using deposit_pct.
     * Additive — nothing is backfilled.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('deposit_amount_myr', 12, 2)->nullable()->after('deposit_pct');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('deposit_amount_myr');
        });
    }
};
