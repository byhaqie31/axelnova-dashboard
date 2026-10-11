<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quotation revisions (docs/global/QUOTATION-REVISIONS.md). A quotation keeps
     * its AXNQ reference for the life of the project; its commercial content lives
     * in numbered revisions (R1, R2, …). `quotations` keeps its content columns as
     * a MIRROR of the current revision, so every existing reader (lists, emails,
     * reports, invoice mapping) keeps working unchanged.
     *
     * Every existing quotation (soft-deleted included) gets exactly one revision
     * R1 carrying a COPY of its current content and status, and inherits the
     * quotation's public_token — so every PDF link already sent still opens the
     * same document. Orders point at that R1. Copy, not move: down() is lossless
     * while no family has more than one revision.
     */
    public function up(): void
    {
        Schema::create('quotation_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            // String + app-level constants (QuotationRevision::STATUSES), not a MySQL
            // enum — a new status is a code change, not an ALTER.
            $table->string('status', 20)->default('draft');
            $table->text('change_reason')->nullable();
            $table->foreignId('replaces_revision_id')->nullable()
                ->constrained('quotation_revisions')->nullOnDelete();
            $table->string('public_token', 64)->nullable()->unique();

            // Content — the same columns (and types) as the quotation row it mirrors.
            $table->unsignedBigInteger('service_package_id')->nullable();
            $table->string('package_key', 80)->nullable();
            $table->unsignedBigInteger('pricing_config_id')->nullable();
            $table->json('form_payload')->nullable();
            $table->json('document')->nullable();
            $table->json('addons')->nullable();
            $table->string('locale', 2)->default('en');
            $table->decimal('estimate_min_myr', 12, 2)->default(0);
            $table->decimal('estimate_max_myr', 12, 2)->default(0);
            $table->unsignedTinyInteger('estimate_eta_value')->default(0);
            $table->string('estimate_eta_unit', 10)->default('week');

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['quotation_id', 'number']);
            $table->index(['quotation_id', 'status']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('current_revision_id')->nullable()->after('reference_code')
                ->constrained('quotation_revisions')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('current_revision_id')->nullable()->after('quotation_id')
                ->constrained('quotation_revisions')->nullOnDelete();
            $table->timestamp('revised_at')->nullable()->after('completed_at');
        });

        $this->backfill();
    }

    /**
     * R1 for every quotation that has no revision yet, then the pointers —
     * verified before commit. Idempotent (families that already have a revision
     * are skipped). Public so the test suite can exercise it without DDL.
     */
    public function backfill(): void
    {
        DB::transaction(function (): void {
            $acceptedAt = DB::table('orders')->pluck('created_at', 'quotation_id');
            $addons = DB::table('quotation_addons')
                ->orderBy('id')
                ->get(['quotation_id', 'addon_key', 'addon_label', 'amount_myr'])
                ->groupBy('quotation_id');

            DB::table('quotations')
                ->whereNotExists(fn ($sub) => $sub->from('quotation_revisions')->whereColumn('quotation_revisions.quotation_id', 'quotations.id'))
                ->orderBy('id')
                ->each(function (object $q) use ($acceptedAt, $addons): void {
                    $revisionId = DB::table('quotation_revisions')->insertGetId([
                        'quotation_id' => $q->id,
                        'number' => 1,
                        'status' => $q->status,
                        'public_token' => $q->public_token,
                        'service_package_id' => $q->service_package_id,
                        'package_key' => $q->package_key,
                        'pricing_config_id' => $q->pricing_config_id,
                        'form_payload' => $q->form_payload,
                        'document' => $q->document,
                        'addons' => json_encode(($addons[$q->id] ?? collect())->map(fn ($a) => [
                            'addon_key' => $a->addon_key,
                            'addon_label' => $a->addon_label,
                            'amount_myr' => (float) $a->amount_myr,
                        ])->values()->all()),
                        'locale' => $q->locale,
                        'estimate_min_myr' => $q->estimate_min_myr,
                        'estimate_max_myr' => $q->estimate_max_myr,
                        'estimate_eta_value' => $q->estimate_eta_value,
                        'estimate_eta_unit' => $q->estimate_eta_unit,
                        'issued_at' => $q->issued_at,
                        'sent_at' => $q->sent_at,
                        'expires_at' => $q->expires_at,
                        // The order's birth is the acceptance moment for pre-revision rows.
                        'accepted_at' => $q->status === 'accepted' ? ($acceptedAt[$q->id] ?? $q->updated_at) : null,
                        'created_at' => $q->created_at,
                        'updated_at' => $q->updated_at,
                    ]);

                    DB::table('quotations')->where('id', $q->id)->update(['current_revision_id' => $revisionId]);
                    DB::table('orders')->where('quotation_id', $q->id)->update(['current_revision_id' => $revisionId]);
                });

            // Verify before committing: one R1 per quotation, every pointer set.
            $quotations = DB::table('quotations')->count();
            $revisions = DB::table('quotation_revisions')->where('number', 1)->count();
            $unpointed = DB::table('quotations')->whereNull('current_revision_id')->count();
            $orphanOrders = DB::table('orders')->whereNull('current_revision_id')->count();
            if ($revisions !== $quotations || $unpointed !== 0 || $orphanOrders !== 0) {
                throw new RuntimeException(
                    "Revision backfill mismatch: {$quotations} quotations, {$revisions} R1 rows, "
                    ."{$unpointed} quotations and {$orphanOrders} orders without a current revision."
                );
            }
        });
    }

    public function down(): void
    {
        // Lossless only while every family is a lone R1 (its content still sits on
        // the quotation row). Later revisions are history that a rollback would
        // destroy — refuse rather than drop it; remove them deliberately first.
        $later = DB::table('quotation_revisions')->where('number', '>', 1)->count();
        if ($later > 0) {
            throw new RuntimeException(
                "Refusing to roll back: {$later} quotation revision(s) beyond R1 exist and would be lost."
            );
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_revision_id');
            $table->dropColumn('revised_at');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_revision_id');
        });

        Schema::dropIfExists('quotation_revisions');
    }
};
