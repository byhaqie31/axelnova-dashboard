<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Receipts — split out of the combined `documents` table. A receipt confirms
     * a settled payment and belongs to the invoice it settles (`invoice_id`),
     * plus its order. Frozen DocumentData snapshot in `payload`.
     */
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('receipt_number', 40)->unique();
            $table->string('public_token', 64)->unique();
            $table->json('payload');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_ref')->nullable();
            $table->string('payment_method')->nullable();
            $table->enum('status', ['issued', 'void'])->default('issued');
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->softDeletes();
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
