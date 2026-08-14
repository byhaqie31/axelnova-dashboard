<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The quotation's issue date, distinct from created_at (audit, immutable) and
     * updated_at (moves on EVERY write, including post-send status flips).
     *
     * Semantics (enforced by Quotation::booted's saving hook):
     *   • re-stamped now() on every save while status is 'draft';
     *   • frozen the moment status leaves 'draft' — never rewritten after that;
     *   • "valid until" is computed from it (issued_at + valid_for_days), never
     *     stored, with a stored expires_at (send flow / admin override) taking
     *     precedence when present.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->timestamp('issued_at')->nullable()->after('submitted_at');
        });

        // Backfill existing rows (soft-deleted included): updated_at is the best
        // available approximation of the last authoring write. Query-builder
        // update, so updated_at itself is not touched.
        DB::table('quotations')
            ->whereNull('issued_at')
            ->update(['issued_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('issued_at');
        });
    }
};
