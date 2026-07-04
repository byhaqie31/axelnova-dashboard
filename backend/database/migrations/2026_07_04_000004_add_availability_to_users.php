<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Self-reported status for the /team workspace Profile page (Task 4 of the
     * portal restructure). Two values only — no "away"/"offline" spectrum, per
     * the brief. Defaults everyone to 'available' so existing rows don't need
     * a backfill decision.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('availability', ['available', 'busy'])
                ->default('available')
                ->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('availability');
        });
    }
};
