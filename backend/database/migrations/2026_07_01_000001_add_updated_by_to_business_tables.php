<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1 — attribution. Stamp who last touched each business record. Nullable
     * (a null updater = a system / gateway write) and nullOnDelete so removing a
     * teammate never blocks deleting their history.
     */
    private const TABLES = [
        'quotations', 'orders', 'inquiries', 'referrals', 'invoices',
        'payments', 'service_categories', 'service_packages', 'projects',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('updated_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('updated_by');
            });
        }
    }
};
