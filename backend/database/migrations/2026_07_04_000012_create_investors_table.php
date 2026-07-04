<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 9 — the investor profile. Mirrors referral_partners as the second
 * partner kind: one row per investor, linked 1:1 to its authenticatable
 * external_accounts row (which carries email + passcode + status). No content
 * model yet (documents/reports are premium empty states for now) — this is the
 * minimal profile spine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('external_account_id')
                ->constrained('external_accounts')
                ->cascadeOnDelete();

            $table->string('name', 150);
            $table->string('company', 150)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investors');
    }
};
