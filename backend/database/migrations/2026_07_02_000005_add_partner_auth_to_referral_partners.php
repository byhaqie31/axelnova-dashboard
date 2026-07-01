<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — make referral_partners authenticatable. `password` holds a hashed
 * passcode (nullable: pending/never-issued partners have none) issued only on
 * marketer approval or reset. `last_login_at` stamps portal sign-ins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_partners', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->timestamp('last_login_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('referral_partners', function (Blueprint $table) {
            $table->dropColumn(['password', 'last_login_at']);
        });
    }
};
