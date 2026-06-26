<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invoices get a payment due date so "overdue" is a real per-invoice property
     * (overdue = status issued AND due_at < today). Defaulted to issued_at + 14
     * days at issue time; existing rows backfilled to the same so the filter is
     * meaningful from day one.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('due_at')->nullable()->after('issued_at');
        });

        DB::table('invoices')->whereNull('due_at')->update([
            'due_at' => DB::raw('DATE(issued_at + INTERVAL 14 DAY)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('due_at');
        });
    }
};
