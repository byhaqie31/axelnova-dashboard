<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money rules for quotation revisions (docs/global/QUOTATION-REVISIONS.md §4):
     *
     *   • invoices.revision_id — the revision whose terms produced the invoice.
     *     Existing invoices are tagged with their order's current revision (R1).
     *   • invoices.void_reason — why a supersede voided it ("Superseded by R2").
     *   • invoices.type gains `adjustment` (the netting top-up), `phase` (one
     *     phase of a phased lump sum, numbered by instalment_no) and `care_plan`
     *     (one month of a separate Care Plan, dated by care_period).
     *   • orders.schedule_netting — the new schedule netted against paid-to-date
     *     at supersede time; orders.revision_flags — overpaid / care-plan-changed.
     *   • order_care_plans — Care Plan coverage periods, closed and reopened on
     *     supersede so no month is billed twice or skipped.
     *
     * Additive — nothing existing changes meaning.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('revision_id')->nullable()->after('order_id')
                ->constrained('quotation_revisions')->nullOnDelete();
            $table->date('care_period')->nullable()->after('instalment_no');
            $table->string('void_reason', 255)->nullable()->after('status');
        });

        DB::statement("ALTER TABLE invoices MODIFY type ENUM('deposit', 'partial', 'final', 'instalment', 'adjustment', 'phase', 'care_plan') NOT NULL DEFAULT 'deposit'");

        DB::table('invoices')
            ->whereNull('revision_id')
            ->update(['revision_id' => DB::raw('(SELECT current_revision_id FROM orders WHERE orders.id = invoices.order_id)')]);

        Schema::table('orders', function (Blueprint $table) {
            $table->json('schedule_netting')->nullable()->after('payment_plan');
            $table->json('revision_flags')->nullable()->after('schedule_netting');
        });

        Schema::create('order_care_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('revision_id')->nullable()->constrained('quotation_revisions')->nullOnDelete();
            // included = paid inside the instalment / partner monthly figure;
            // separate = its own monthly subscription, billed by care_plan invoices.
            $table->string('kind', 12);
            $table->decimal('monthly_myr', 12, 2)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['order_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        $added = DB::table('invoices')->whereIn('type', ['adjustment', 'phase', 'care_plan'])->count();
        if ($added > 0) {
            throw new RuntimeException("Refusing to roll back: {$added} adjustment / phase / care-plan invoice(s) exist and would be lost.");
        }

        Schema::dropIfExists('order_care_plans');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['schedule_netting', 'revision_flags']);
        });

        DB::statement("ALTER TABLE invoices MODIFY type ENUM('deposit', 'partial', 'final', 'instalment') NOT NULL DEFAULT 'deposit'");

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revision_id');
            $table->dropColumn(['care_period', 'void_reason']);
        });
    }
};
