<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->renameColumn('estimate_weeks', 'estimate_eta_value');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->string('estimate_eta_unit', 10)->default('week')->after('estimate_eta_value');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('estimate_eta_unit');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->renameColumn('estimate_eta_value', 'estimate_weeks');
        });
    }
};
