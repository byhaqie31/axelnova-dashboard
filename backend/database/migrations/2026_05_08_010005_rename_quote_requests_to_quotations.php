<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename addons table first — drop old FK, rename, rename column, re-add FK.
        Schema::table('quote_request_addons', function (Blueprint $table) {
            $table->dropForeign(['quote_request_id']);
        });

        Schema::rename('quote_request_addons', 'quotation_addons');
        Schema::rename('quote_requests', 'quotations');

        Schema::table('quotation_addons', function (Blueprint $table) {
            $table->renameColumn('quote_request_id', 'quotation_id');
        });

        Schema::table('quotation_addons', function (Blueprint $table) {
            $table->foreign('quotation_id')
                ->references('id')
                ->on('quotations')
                ->cascadeOnDelete();
        });

        // Add new columns + promote existing client_id to a real FK.
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('package_key', 80)->nullable()->after('service_package_id');

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('package_key');
        });

        Schema::table('quotation_addons', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
        });

        Schema::table('quotation_addons', function (Blueprint $table) {
            $table->renameColumn('quotation_id', 'quote_request_id');
        });

        Schema::rename('quotations', 'quote_requests');
        Schema::rename('quotation_addons', 'quote_request_addons');

        Schema::table('quote_request_addons', function (Blueprint $table) {
            $table->foreign('quote_request_id')
                ->references('id')
                ->on('quote_requests')
                ->cascadeOnDelete();
        });
    }
};
