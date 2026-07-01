<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 0 — replace the free-string `role` ('admin' by default) with the
     * four-role tier enum. The single seeded admin is re-homed to `founder`
     * BEFORE the column is narrowed: the new enum has no 'admin' member, so
     * mapping while it's still a string avoids a strict-mode truncation on
     * the ALTER.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'admin')->update(['role' => 'founder']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['founder', 'partner', 'marketer', 'engineer'])
                ->default('engineer')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admin')->change();
        });

        DB::table('users')->where('role', 'founder')->update(['role' => 'admin']);
    }
};
