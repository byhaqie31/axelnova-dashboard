<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The money ledger — the single source of truth. One row per movement,
     * including refunds (a negative `amount_myr` pointing at its original via
     * `parent_payment_id`) and failed attempts. Order / invoice paid caches are
     * derived from `SUM(amount_myr)` over succeeded rows by PaymentObserver;
     * nothing else writes those caches.
     *
     * String columns + PHP backed-enum casts (not MySQL enums) so adding a
     * method / gateway / status later is a code change, not an ALTER.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();            // PAY-AXNQ-2026-0006 (-2, -3 on repeat)

            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();               // allocation target
            $table->foreignId('client_id')->constrained()->restrictOnDelete();                         // denormalised for ledger queries
            $table->foreignId('parent_payment_id')->nullable()->constrained('payments')->nullOnDelete(); // refunds → original
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();          // manual entries

            $table->string('type')->default('payment');            // payment | refund
            $table->string('gateway')->default('manual');          // stripe | billplz | manual
            $table->string('method');                              // card | fpx | duitnow | bank_transfer | cash | ewallet | other
            $table->string('status')->default('pending');          // pending | succeeded | failed | refunded | cancelled

            $table->decimal('amount_myr', 12, 2);                  // SIGNED — negative for refunds
            $table->decimal('fee_myr', 12, 2)->default(0);         // gateway fee, 0 for manual
            $table->decimal('net_myr', 12, 2)->nullable();         // amount - fee (settlement); nullable until known
            $table->string('currency', 3)->default('MYR');

            $table->string('gateway_payment_id')->nullable();      // pi_… / billplz bill id
            $table->json('gateway_payload')->nullable();           // raw, for audit
            $table->string('idempotency_key')->nullable();
            $table->string('reference')->nullable();               // internal note / external ref
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // One ledger row per gateway charge. NULLs (manual rows) don't collide
            // under MySQL's multi-NULL unique semantics.
            $table->unique(['gateway', 'gateway_payment_id']);
            $table->unique('idempotency_key');
            $table->index(['order_id', 'status']);
            $table->index(['invoice_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
