<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The pick-up moment. Claiming (or starting an admin-assigned) task flips it to
 * in_progress but never captured WHEN — so the task detail timeline could show
 * "opened" and "completed" but not "picked up". `started_at` stamps that edge;
 * it resets to null on release (back to the pool) so a re-claim reads fresh.
 * Nullable + no backfill: tasks already in flight simply won't show the pickup
 * event until they're next claimed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('started_at');
        });
    }
};
