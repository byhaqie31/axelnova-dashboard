<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 7 — turn each payslip into an itemised record: allowance snapshot + Σ task
 * extras, with `gross_myr` kept as the TOTAL (allowance + extras) so every legacy
 * consumer stays valid. `paid_at` remains the sole settlement marker (no new
 * status column).
 *
 *   - `allowance_snapshot_myr`  the member's monthly_allowance_myr FROZEN at
 *                               generation (nullable: legacy rows stay null;
 *                               null vs 0 distinction is preserved faithfully).
 *   - `task_extras_myr`         Σ of the payment_pending task bonuses this slip
 *                               settles (default 0; legacy rows read 0).
 *   - UNIQUE (user_id, period_label)  the double-counting guard — one payslip per
 *                               member per period.
 *
 * ⚠ Live-data risk: the unique index fails to build if production already has two
 * rows sharing (user_id, period_label). Dedup first — check with:
 *   SELECT user_id, period_label, COUNT(*) c FROM payroll_entries
 *   GROUP BY user_id, period_label HAVING c > 1;
 * Dev's two legacy rows are distinct users, so they don't collide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->unsignedInteger('allowance_snapshot_myr')->nullable()->after('period_label');
            $table->unsignedInteger('task_extras_myr')->default(0)->after('allowance_snapshot_myr');

            $table->unique(['user_id', 'period_label']);
        });
    }

    public function down(): void
    {
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'period_label']);
            $table->dropColumn(['allowance_snapshot_myr', 'task_extras_myr']);
        });
    }
};
