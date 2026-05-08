<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1 — expand the status enum so 'accepted' is valid before we migrate data.
        DB::statement("ALTER TABLE quotations MODIFY status ENUM('new','viewed','contacted','converted','accepted','rejected','spam') NOT NULL DEFAULT 'new'");

        // Step 2 — converted quotations already produced an order (backfill step). Mark them 'accepted'.
        DB::table('quotations')->where('status', 'converted')->update(['status' => 'accepted']);

        // Step 3 — drop 'converted' from the enum.
        DB::statement("ALTER TABLE quotations MODIFY status ENUM('new','viewed','contacted','accepted','rejected','spam') NOT NULL DEFAULT 'new'");

        // Step 4 — clear stale service_package_id (values were never validated against a real table).
        DB::table('quotations')->update(['service_package_id' => null]);

        // Step 5 — drop project_status index, then orphan + project_* columns.
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['project_status']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn([
                'service_category_id',
                'quotation_id',
                'project_status',
                'project_started_at',
                'project_delivered_at',
                'project_completed_at',
            ]);
        });

        // Step 6 — promote service_package_id to a proper FK now that service_packages exists.
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('service_package_id')
                ->references('id')
                ->on('service_packages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['service_package_id']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('service_category_id')->nullable()->after('company');
            $table->unsignedBigInteger('quotation_id')->nullable()->after('client_id');
            $table->enum('project_status', ['pending', 'in_progress', 'delivered', 'completed'])
                ->nullable()
                ->after('status');
            $table->timestamp('project_started_at')->nullable()->after('project_status');
            $table->timestamp('project_delivered_at')->nullable()->after('project_started_at');
            $table->timestamp('project_completed_at')->nullable()->after('project_delivered_at');
            $table->index('project_status');
        });

        DB::statement("ALTER TABLE quotations MODIFY status ENUM('new','viewed','contacted','converted','accepted','rejected','spam') NOT NULL DEFAULT 'new'");
        DB::table('quotations')->where('status', 'accepted')->update(['status' => 'converted']);
        DB::statement("ALTER TABLE quotations MODIFY status ENUM('new','viewed','contacted','converted','rejected','spam') NOT NULL DEFAULT 'new'");
    }
};
