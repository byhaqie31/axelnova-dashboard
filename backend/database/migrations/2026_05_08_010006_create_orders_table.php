<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('quotation_id')
                ->constrained('quotations')
                ->restrictOnDelete();
            $table->foreignId('client_id')
                ->constrained('clients')
                ->restrictOnDelete();
            $table->decimal('value_min_myr', 12, 2);
            $table->decimal('value_max_myr', 12, 2);
            $table->enum('status', ['pending', 'in_progress', 'delivered', 'completed', 'cancelled'])
                ->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
