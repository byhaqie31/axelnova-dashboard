<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expected completion date — the SLA / delivery target for an order.
     * Defaulted from the source quotation's ETA when the order is born, and
     * editable thereafter so the admin can track work against a deadline.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->date('due_at')->nullable()->after('completed_at');
            $table->index('due_at');
        });

        // Backfill existing orders: created date + the quotation's ETA.
        foreach (['hour' => 'HOUR', 'day' => 'DAY', 'week' => 'WEEK', 'month' => 'MONTH'] as $unit => $sqlUnit) {
            DB::statement(
                "UPDATE orders o
                 JOIN quotations q ON q.id = o.quotation_id
                 SET o.due_at = DATE_ADD(o.created_at, INTERVAL q.estimate_eta_value {$sqlUnit})
                 WHERE q.estimate_eta_unit = ? AND q.estimate_eta_value > 0 AND o.due_at IS NULL",
                [$unit],
            );
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['due_at']);
            $table->dropColumn('due_at');
        });
    }
};
