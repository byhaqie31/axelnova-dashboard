<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('active');
        });

        $default = DB::table('service_categories')->where('slug', 'web')->first()
            ?? DB::table('service_categories')->orderBy('sort_order')->first();

        if ($default) {
            DB::table('service_categories')->where('id', $default->id)->update(['is_default' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
