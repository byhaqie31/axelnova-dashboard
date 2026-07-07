<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team-member profile fields — contact, bank, and address. All nullable and
 * teammate-filled (self-serve on /team/profile); the founder only reads them.
 * Prep for a company merge; drives the profile-completeness flag + onboarding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->after('monthly_allowance_myr');
            $table->string('bank_name', 120)->nullable()->after('phone');
            $table->string('bank_account_number', 60)->nullable()->after('bank_name');
            $table->string('bank_account_holder', 150)->nullable()->after('bank_account_number');
            $table->string('address_line1', 200)->nullable()->after('bank_account_holder');
            $table->string('address_line2', 200)->nullable()->after('address_line1');
            $table->string('city', 100)->nullable()->after('address_line2');
            $table->string('postcode', 20)->nullable()->after('city');
            $table->string('state', 100)->nullable()->after('postcode');
            $table->string('country', 100)->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'bank_name',
                'bank_account_number',
                'bank_account_holder',
                'address_line1',
                'address_line2',
                'city',
                'postcode',
                'state',
                'country',
            ]);
        });
    }
};
