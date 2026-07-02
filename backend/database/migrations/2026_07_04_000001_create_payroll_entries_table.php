<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — the in-system payroll ledger, record-only by design. One row per
 * payslip: gross is entered as agreed, never computed — statutory maths
 * (EPF/SOCSO/EIS/PCB) is explicitly out of scope (plan Section 3) and belongs
 * to a payroll SaaS. `user_id` is whose payslip it is; `created_by` is the
 * founder who recorded it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('period_label', 40);      // 'Jun 2026' — a label, not a computed period
            $table->unsignedInteger('gross_myr');    // record only — no statutory breakdown
            $table->timestamp('paid_at')->nullable();
            $table->string('method', 40)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
    }
};
