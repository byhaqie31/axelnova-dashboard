<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_configs', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique();
            $table->json('config');
            $table->boolean('active')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['active', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_configs');
    }
};
