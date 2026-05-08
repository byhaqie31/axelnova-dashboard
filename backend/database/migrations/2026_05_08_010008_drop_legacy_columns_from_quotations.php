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
        // Idempotent: re-issuing MODIFY with the same shape is a no-op in MySQL.
        DB::statement("ALTER TABLE quotations MODIFY status ENUM('new','viewed','contacted','converted','accepted','rejected','spam') NOT NULL DEFAULT 'new'");

        // Step 2 — converted quotations already produced an order (backfill step). Mark them 'accepted'.
        DB::table('quotations')->where('status', 'converted')->update(['status' => 'accepted']);

        // Step 3 — drop 'converted' from the enum.
        DB::statement("ALTER TABLE quotations MODIFY status ENUM('new','viewed','contacted','accepted','rejected','spam') NOT NULL DEFAULT 'new'");

        // Step 4 — clear stale service_package_id (values were never validated against a real table).
        DB::table('quotations')->update(['service_package_id' => null]);

        // Step 5 — drop the project_status index. MySQL keeps the original index name across
        // RENAME TABLE, so this index might be named after the old `quote_requests` table on
        // installs where the create-index migration ran before the rename, or the new
        // `quotations` name on installs where the migrations were squashed onto a fresh DB.
        // Drop whichever is present.
        $this->dropIndexIfExists('quotations', 'quote_requests_project_status_index');
        $this->dropIndexIfExists('quotations', 'quotations_project_status_index');

        // Step 6 — drop orphan + project_* columns. Wrap each in IF EXISTS via information_schema
        // so partial reruns after a previous failure don't trip on already-dropped columns.
        $this->dropColumnIfExists('quotations', 'service_category_id');
        $this->dropColumnIfExists('quotations', 'quotation_id');
        $this->dropColumnIfExists('quotations', 'project_status');
        $this->dropColumnIfExists('quotations', 'project_started_at');
        $this->dropColumnIfExists('quotations', 'project_delivered_at');
        $this->dropColumnIfExists('quotations', 'project_completed_at');

        // Step 7 — promote service_package_id to a proper FK now that service_packages exists.
        // Skip if a constraint already exists (rerun safety).
        if (!$this->foreignKeyExists('quotations', 'service_package_id')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->foreign('service_package_id')
                    ->references('id')
                    ->on('service_packages')
                    ->nullOnDelete();
            });
        }
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

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $indexName],
        );

        if ((int) $exists->c > 0) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        }
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column],
        );

        if ((int) $exists->c > 0) {
            DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
        }
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        $exists = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.key_column_usage
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? AND referenced_table_name IS NOT NULL",
            [$table, $column],
        );

        return (int) $exists->c > 0;
    }
};
