<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — the marketing-spend ledger, record-only. `entered_by` is both the
 * attribution and the visibility scope: the marketer sees only their own rows
 * (/v1/team), founder + partner see the full roll-up (/v1/admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entered_by')->constrained('users');
            $table->string('category', 60);
            $table->unsignedInteger('amount_myr');
            $table->date('spent_at');
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index('spent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_expenses');
    }
};
