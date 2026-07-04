<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 7 — the team member's monthly allowance (their standing base comp, in
 * whole ringgit). Nullable by design: null means "no allowance on file" (a
 * contractor paid purely per task), distinct from an explicit 0. Payslip
 * generation SNAPSHOTS this value onto the payroll_entries row so a later raise
 * never rewrites history. Editing this lives on the Users admin page (next task),
 * never on Payroll.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('monthly_allowance_myr')->nullable()->after('availability');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('monthly_allowance_myr');
        });
    }
};
