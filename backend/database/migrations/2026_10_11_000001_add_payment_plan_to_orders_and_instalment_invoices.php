<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invoicing follows the quotation's payment plan:
     *   • orders.payment_plan — the instalment / partner plan agreed on the
     *     quotation, snapshotted on accept (months, monthly amount, billing day,
     *     the RESOLVED first date, care-plan flag). Null for a lump sum. Orders
     *     accepted before this read the plan from their quotation (no backfill).
     *   • invoices.type gains `instalment`, and invoices.instalment_no ties such
     *     an invoice to its place in the schedule (one live invoice per number).
     * Additive — nothing existing changes.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('payment_plan')->nullable()->after('deposit_amount_myr');
        });

        DB::statement("ALTER TABLE invoices MODIFY type ENUM('deposit', 'partial', 'final', 'instalment') NOT NULL DEFAULT 'deposit'");

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedSmallInteger('instalment_no')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('instalment_no');
        });

        DB::statement("ALTER TABLE invoices MODIFY type ENUM('deposit', 'partial', 'final') NOT NULL DEFAULT 'deposit'");

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_plan');
        });
    }
};
