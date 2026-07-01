<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — the normalized referrer entity. One row per affiliate (the `Referrer`
 * model); each referred company is a `Referral` pointing back here. `code` is the
 * neutral link param (`?ref=CODE`). `commission_pct` is derived from the tier and
 * stored as programme config, but the actual money owed stays derived (pct ×
 * collected order value) and is never persisted. Auth columns (password,
 * last_login_at) are added later by the Phase 4 migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_partners', function (Blueprint $table) {
            $table->id();

            $table->string('code', 32)->unique();            // link param value: ?ref=CODE
            $table->string('name', 150);
            $table->string('email', 200)->unique();
            $table->string('phone', 30)->nullable();

            $table->enum('relationship_tier', ['cold', 'warm', 'closed'])->default('cold');
            $table->unsignedTinyInteger('commission_pct');   // derived from tier (5/10/15)

            $table->boolean('agreed_terms')->default(false);

            // pending until a marketer approves; paused = access frozen without deletion.
            $table->enum('status', ['pending', 'active', 'paused'])->default('pending');

            // Attribution — who last touched this record (null = system/backfill).
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_partners');
    }
};
