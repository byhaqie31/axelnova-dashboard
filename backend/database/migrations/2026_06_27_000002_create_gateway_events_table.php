<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Raw inbound webhook log + idempotency gate for the gateway phases. A
     * redelivered event (same `event_id`) is a no-op, so a charge is never
     * double-counted into the ledger. Empty until Billplz/Stripe land.
     */
    public function up(): void
    {
        Schema::create('gateway_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');                             // stripe | billplz
            $table->string('event_id')->unique();                  // Stripe evt_… ; Billplz id/signature
            $table->string('type')->nullable();                    // e.g. payment_intent.succeeded
            $table->json('payload');                               // raw inbound body
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_events');
    }
};
