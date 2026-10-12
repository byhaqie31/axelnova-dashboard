<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partners › Collaborations makes partner rows editable from the admin, so they
 * carry the same "who last touched it" stamp as the other admin-edited tables
 * (RecordsActivity). Additive and nullable — existing rows stay as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaboration_partners', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('sort_order')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collaboration_partners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
