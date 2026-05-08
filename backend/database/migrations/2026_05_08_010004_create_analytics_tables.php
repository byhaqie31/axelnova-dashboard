<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path', 500);
            $table->char('ip_hash', 64);
            $table->string('user_agent', 500)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->timestamp('viewed_at')->useCurrent();

            // Append-only — no updated_at to keep inserts cheap.
            $table->index(['path', 'viewed_at']);
            $table->index('viewed_at');
        });

        Schema::create('entity_likes', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50); // e.g. 'project', 'service_package'
            $table->unsignedBigInteger('entity_id');
            $table->char('ip_hash', 64);
            $table->uuid('cookie_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // One like per (entity, ip) to deter spam without forcing auth.
            $table->unique(['entity_type', 'entity_id', 'ip_hash']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_likes');
        Schema::dropIfExists('page_views');
    }
};
