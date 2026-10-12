<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time access links (set-up / reset) for team members, partners and the
     * founder — Phase 2 spec docs/superpowers/specs/2026-10-12-email-phase-2-security-design.md.
     * The token itself only ever exists in the email; this table holds its
     * SHA-256 hash, its lifetime and whether it has been used.
     */
    public function up(): void
    {
        Schema::create('access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('account_type', 30);   // user · external_account
            $table->unsignedBigInteger('account_id');
            $table->string('purpose', 10);         // setup · reset
            $table->string('surface', 10);         // team · admin · partner — which page the link opens
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['account_type', 'account_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_tokens');
    }
};
