<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invoices — split out of the combined `documents` table. An invoice is a
     * frozen DocumentData snapshot (the PDF renders from `payload`); `type`
     * distinguishes a deposit / partial / final bill. Settling one records a
     * payment on the parent order and stamps `paid_at`.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('invoice_number', 40)->unique();
            $table->string('public_token', 64)->unique();
            $table->enum('type', ['deposit', 'partial', 'final'])->default('deposit');
            $table->json('payload');
            $table->decimal('amount_total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->nullable();
            $table->string('payment_ref')->nullable();
            $table->string('payment_method')->nullable();
            $table->enum('status', ['issued', 'paid', 'void'])->default('issued');
            $table->timestamp('issued_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['order_id', 'type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
