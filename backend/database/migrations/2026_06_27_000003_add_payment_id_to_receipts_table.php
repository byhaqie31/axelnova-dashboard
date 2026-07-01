<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Receipts anchor to the payment that produced them (1 payment : 1 receipt).
     * `invoice_id` stays for display / allocation only — a deposit paid before
     * any invoice is issued still produces a receipt off its payment.
     * `amount` remains part of the frozen snapshot.
     */
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('invoice_id')
                ->constrained()->nullOnDelete();
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });
    }
};
