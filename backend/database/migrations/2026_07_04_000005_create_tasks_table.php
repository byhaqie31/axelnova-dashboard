<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 5 — the Tasks engine. One row per unit of work the founder delegates.
 * `created_by` is the admin who authored it; `assignee_id` is who's doing it
 * (null = the pick-up pool a team member can self-assign from). `pay_amount_myr`
 * is nullable by design — null means "covered by the monthly allowance", a value
 * means "extra on top" (the money lifecycle: payment_pending → paid). The status
 * enum is the workflow spine (open → in_progress → completed / payment_pending →
 * paid); `notes` is the append-only daily log team members stamp on updates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            $table->string('title', 200);
            $table->text('description')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('pay_amount_myr')->nullable();  // null = allowance, value = extra on top
            $table->string('duration_estimate', 60)->nullable();    // free text: '2h', '3 days'
            $table->dateTime('deadline')->nullable();

            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'completed', 'payment_pending', 'paid'])->default('open');

            $table->text('notes')->nullable();      // append-only team update / daily log

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('assignee_id');
            $table->index('deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
