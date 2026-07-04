<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 8 — the Users admin page needs a persistent "deactivated" marker.
 * Deactivation previously only revoked tokens (sign-out-everywhere, not a
 * lock — see the old UsersController@deactivate docblock); that let a
 * deactivated teammate simply log back in. Nullable timestamp: null = active,
 * set = deactivated (and by whom/when, for free). Login on both /v1/admin and
 * /v1/team rejects an account with this set; deactivating also still revokes
 * existing tokens so an already-open session dies immediately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('monthly_allowance_myr');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
