<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Where the quotation originated. New rows are admin-built; existing
            // rows came from the (now retired) public self-serve funnel.
            $table->string('source', 20)->default('admin')->after('reference_code');
            // Unguessable token for the public PDF link (render-on-demand).
            $table->string('public_token', 64)->nullable()->unique()->after('source');
            // The presentable quotation document for the PDF (project, line items,
            // terms, deposit %, tax) — admin-authored, distinct from the engine estimate.
            $table->json('document')->nullable()->after('form_payload');
            $table->timestamp('sent_at')->nullable()->after('viewed_at');
        });

        // Backfill: everything that exists today predates the admin builder.
        DB::table('quotations')->update(['source' => 'self_serve']);

        // Widen status from a fixed enum to a varchar so the lifecycle can grow
        // (draft → sent → accepted → declined → expired) alongside legacy values.
        DB::statement("ALTER TABLE quotations MODIFY status VARCHAR(20) NOT NULL DEFAULT 'new'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE quotations MODIFY status ENUM('new','viewed','contacted','accepted','rejected','spam') NOT NULL DEFAULT 'new'");

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn(['source', 'public_token', 'document', 'sent_at']);
        });
    }
};
