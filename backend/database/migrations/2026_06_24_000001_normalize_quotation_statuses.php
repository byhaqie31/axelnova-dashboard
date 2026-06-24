<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Collapse the legacy self-serve lead statuses onto the simplified quotation
     * lifecycle, then constrain the column to that set (it had drifted to an
     * unconstrained varchar default 'new'). Target: draft → sent → accepted, plus
     * rejected / expired. Lead-tracking now lives on inquiries, not quotations.
     */
    public function up(): void
    {
        // Un-progressed leads become drafts; junk/declined become rejected.
        DB::table('quotations')->whereIn('status', ['new', 'viewed', 'contacted'])->update(['status' => 'draft']);
        DB::table('quotations')->whereIn('status', ['spam', 'declined', 'converted'])->update(['status' => 'rejected']);

        // Safety net so the ENUM tighten can't fail on an unforeseen value.
        DB::table('quotations')
            ->whereNotIn('status', ['draft', 'sent', 'accepted', 'rejected', 'expired'])
            ->update(['status' => 'draft']);

        DB::statement("ALTER TABLE quotations MODIFY status ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE quotations MODIFY status VARCHAR(20) NOT NULL DEFAULT 'new'");
    }
};
