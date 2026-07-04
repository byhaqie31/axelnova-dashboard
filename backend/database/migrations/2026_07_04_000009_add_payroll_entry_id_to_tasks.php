<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 7 — stamp which payslip settles each task's extra bonus. Null = not yet on
 * any payslip (the per-task double-count guard: generation only picks up tasks
 * that are payment_pending AND unlinked). nullOnDelete so deleting a payslip
 * releases its tasks back to the unlinked pool rather than cascading them away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('payroll_entry_id')
                ->nullable()
                ->after('status')
                ->constrained('payroll_entries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_entry_id');
        });
    }
};
