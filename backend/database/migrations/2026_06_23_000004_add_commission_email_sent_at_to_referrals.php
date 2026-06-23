<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track when the "we owe you a commission — send your bank details" email
     * was last sent to a referrer, so the admin can see it went out and resend
     * if needed. The commission amount itself stays derived from the linked
     * order's final value × the referral's tier, never duplicated here.
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->timestamp('commission_email_sent_at')->nullable()->after('linked_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('commission_email_sent_at');
        });
    }
};
