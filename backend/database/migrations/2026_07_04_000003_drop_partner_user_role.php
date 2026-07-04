<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the `partner` RBAC role (users.role). It was always overloaded with
     * the unrelated "referral partner" affiliate concept (Referrer model on
     * referral_partners, auth:referral guard, /v1/partner/* — untouched here).
     * Backfill FIRST: any `partner` user becomes `founder` before the column is
     * narrowed, same reasoning as 2026_07_01_000000_expand_user_roles.php — the
     * new enum has no 'partner' member, so mapping while it's still a string
     * avoids a strict-mode truncation on the ALTER.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'partner')->update(['role' => 'founder']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['founder', 'marketer', 'engineer'])
                ->default('engineer')
                ->change();
        });
    }

    /**
     * Restores the 4-value enum, but any row backfilled to `founder` by up()
     * stays `founder` — which of those rows were originally `partner` is
     * unrecoverable, so this is not a true inverse.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['founder', 'partner', 'marketer', 'engineer'])
                ->default('engineer')
                ->change();
        });
    }
};
