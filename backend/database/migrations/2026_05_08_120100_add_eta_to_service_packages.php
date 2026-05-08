<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_packages', function (Blueprint $table) {
            $table->unsignedSmallInteger('eta_value')->default(4)->after('duration_text');
            $table->string('eta_unit', 10)->default('week')->after('eta_value');
        });
    }

    public function down(): void
    {
        Schema::table('service_packages', function (Blueprint $table) {
            $table->dropColumn(['eta_value', 'eta_unit']);
        });
    }
};
