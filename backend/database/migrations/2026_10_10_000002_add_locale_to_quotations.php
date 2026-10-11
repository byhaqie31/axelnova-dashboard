<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The language of a quotation's PDF template chrome (section headings,
     * table captions, row labels, footer, schedule dates) — `en` (default) or
     * `bm`. Set explicitly by the admin builder / the MCP connector, never
     * detected from the founder's content, which prints as authored whatever
     * the locale. Additive — every existing row renders in English as before.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('locale', 2)->default('en')->after('document');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
