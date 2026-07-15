<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One ledger, two kinds. A payslip becomes either a `monthly` recurring run
 * (allowance snapshot + task extras, as before) or a `one_time` record — a
 * signing/festive/spot bonus or an ad-hoc payout, sitting in the SAME table so
 * the payment history and year-to-date totals stay in one place.
 *
 *   - `kind`               'monthly' (default; every existing row backfills to it,
 *                          so history is untouched) or 'one_time'.
 *   - `one_time_type`      the reason label for one-offs (signing/festive/…);
 *                          null for monthly.
 *   - `discretionary_myr`  the manually-entered one-off amount, kept SEPARATE from
 *                          `task_extras_myr` (still task-linked) and
 *                          `allowance_snapshot_myr` (monthly only). gross is always
 *                          allowance(0-if-null) + task_extras + discretionary.
 *
 * The "one slip per period" guard must NOT apply to one-offs (you can record
 * several in a month). We keep a DB-level backstop by moving the unique index onto
 * a generated `monthly_period` column that is period_label for monthly rows and
 * NULL for one-offs — MySQL lets multiple NULLs coexist, so one-offs are
 * unconstrained while the monthly guard the codebase relies on stays enforced.
 * One-offs still carry a YYYY-MM `period_label` (the payment's month) so every
 * existing year rollup keeps working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) New columns. kind defaults to 'monthly' so existing rows backfill.
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->string('kind', 16)->default('monthly')->after('user_id');
            $table->string('one_time_type', 24)->nullable()->after('period_label');
            $table->unsignedInteger('discretionary_myr')->default(0)->after('task_extras_myr');
        });

        // 2) Monthly-scoped guard via a generated column (kind + period_label now
        //    both exist): period_label for monthly rows, NULL for one-offs. Adding
        //    this BEFORE dropping the old index matters — the old composite unique
        //    is currently the ONLY index covering user_id, so it silently backs the
        //    user_id foreign key. This new (user_id, monthly_period) unique gives
        //    the FK another index to lean on, so step 3's drop won't 1553.
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->string('monthly_period', 40)->nullable()
                ->storedAs("case when kind = 'monthly' then period_label else null end")
                ->after('period_label');
            $table->unique(['user_id', 'monthly_period']);
        });

        // 3) Retire the raw unique — it would block a one-off sharing a month with
        //    the monthly slip.
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'period_label']);
        });
    }

    public function down(): void
    {
        // Re-add the old unique first so the user_id FK has an index to lean on
        // before we drop the generated column's unique.
        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->unique(['user_id', 'period_label']);
        });

        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'monthly_period']);
            $table->dropColumn('monthly_period');
        });

        Schema::table('payroll_entries', function (Blueprint $table) {
            $table->dropColumn(['kind', 'one_time_type', 'discretionary_myr']);
        });
    }
};
