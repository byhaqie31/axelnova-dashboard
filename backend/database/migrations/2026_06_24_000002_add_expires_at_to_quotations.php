<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a quotation is sent it gets an expiry date (sent_at + valid_for_days).
     * A sent quote past this date is lazily flipped to 'expired' on next read — no
     * scheduler needed. Indexed because that read-time sweep filters on it.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('sent_at')->index();
        });

        // Backfill existing sent quotes so they participate in lazy expiry too.
        // valid_for_days isn't readily joinable here, so use the platform default (30).
        DB::statement(
            "UPDATE quotations SET expires_at = DATE_ADD(sent_at, INTERVAL 30 DAY)
             WHERE status = 'sent' AND sent_at IS NOT NULL AND expires_at IS NULL"
        );
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
