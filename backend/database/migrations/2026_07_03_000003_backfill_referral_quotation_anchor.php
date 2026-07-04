<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Existing converted referrals point at an order; adopt that order's quotation
        // as the new anchor, and stamp the credited partner onto the quotation.
        $rows = DB::table('referrals')
            ->whereNotNull('linked_order_id')
            ->whereNull('quotation_id')
            ->get(['id', 'linked_order_id', 'referral_partner_id']);

        foreach ($rows as $r) {
            $quotationId = DB::table('orders')->where('id', $r->linked_order_id)->value('quotation_id');
            if (! $quotationId) {
                continue;
            }
            DB::table('referrals')->where('id', $r->id)->update(['quotation_id' => $quotationId]);
            if ($r->referral_partner_id) {
                DB::table('quotations')->where('id', $quotationId)
                    ->whereNull('referral_partner_id')
                    ->update(['referral_partner_id' => $r->referral_partner_id]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive backfill; nothing to reverse (columns dropped by 000001/000002 down).
    }
};
