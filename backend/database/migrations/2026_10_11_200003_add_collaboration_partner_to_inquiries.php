<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The /collaborations pages feed the same inquiries pipeline. Two new origins:
 * `partner` (a client hiring a partner studio through Axel Nova — tagged with
 * the partner) and `collaboration` (a studio applying to partner with us).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->enum('origin', ['quote', 'contact', 'partner', 'collaboration'])->default('quote')->change();
            $table->foreignId('collaboration_partner_id')->nullable()->after('referral_partner_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Fold the new origins back into 'contact' so the narrower enum accepts every row.
        DB::table('inquiries')->whereIn('origin', ['partner', 'collaboration'])->update(['origin' => 'contact']);

        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collaboration_partner_id');
            $table->enum('origin', ['quote', 'contact'])->default('quote')->change();
        });
    }
};
