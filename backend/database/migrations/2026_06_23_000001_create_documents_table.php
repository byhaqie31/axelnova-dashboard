<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issued invoices and receipts. Each row is a FROZEN snapshot: `payload` is the
 * exact `DocumentData` rendered at issue time, so regenerating the PDF can never
 * drift if the underlying order changes later. The PDF itself is not stored —
 * it's rendered on demand from `payload` (token-gated, like quotations).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->enum('type', ['invoice', 'receipt']);
            // Derived from the order's quotation ref, e.g. INV-AXN-2026-0011.
            $table->string('number', 40)->unique();
            $table->string('public_token', 64)->unique();
            // Frozen DocumentData (includes its own `layout`).
            $table->json('payload');
            $table->decimal('amount_total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->nullable();
            $table->string('payment_ref')->nullable();
            $table->string('payment_method')->nullable();
            $table->enum('status', ['issued', 'paid', 'void'])->default('issued');
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
