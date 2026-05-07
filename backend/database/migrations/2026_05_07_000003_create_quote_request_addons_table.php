<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_request_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')
                ->constrained('quote_requests')
                ->cascadeOnDelete();
            $table->string('addon_key', 50);
            $table->string('addon_label', 150);
            $table->decimal('amount_myr', 12, 2);
            $table->timestamps();

            $table->index('quote_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_request_addons');
    }
};
