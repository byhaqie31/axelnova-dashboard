<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 9 — the unified partner-portal identity. One authenticatable row per
 * portal login, discriminated by `type` (referrer | investor). Credentials
 * (email + hashed passcode) move here off the per-profile tables so a single
 * isolated Sanctum guard (`external`, config/auth.php) serves both partner
 * kinds. The referrer/investor PROFILE data stays in its own table
 * (referral_partners / investors), each linking back here by FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_accounts', function (Blueprint $table) {
            $table->id();

            $table->enum('type', ['referrer', 'investor']);
            $table->string('email', 200)->unique();

            // Hashed passcode. Nullable so a profile can exist before it's
            // credentialed (an approved referrer / provisioned investor).
            $table->string('password')->nullable();

            // active = can sign in; suspended = access frozen without deletion.
            $table->enum('status', ['active', 'suspended'])->default('active');

            $table->timestamp('last_login_at')->nullable();

            $table->timestamps();

            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_accounts');
    }
};
