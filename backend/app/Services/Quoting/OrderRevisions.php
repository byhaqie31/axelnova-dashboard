<?php

namespace App\Services\Quoting;

use App\Models\Order;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\Referral;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;

/**
 * The order side of quotation revisions (docs/global/QUOTATION-REVISIONS.md §1.3,
 * §3). An order is the PROJECT: created once, on the family's first acceptance,
 * and it survives revisions — accepting a later revision re-points the same
 * order (same id, same AXNO number) at the new terms. Cancelling an order means
 * the project is off; it is never the way to change terms.
 *
 * Called inside QuotationRevisions::accept()'s transaction.
 */
class OrderRevisions
{
    /** First acceptance: the order is born on the accepted revision's terms. */
    public static function open(Quotation $quotation, QuotationRevision $revision, Quotation $terms, ?int $commissionPct = null): Order
    {
        $order = Order::create(array_merge(self::termsOf($terms), [
            'order_number' => ReferenceCodeGenerator::generate(DocumentType::Order),
            'quotation_id' => $quotation->id,
            'current_revision_id' => $revision->id,
            'client_id' => $quotation->client_id,
            'amount_paid_myr' => 0,
            'due_at' => $terms->dueDateFrom(),
            'status' => 'pending',
        ]));

        $referral = Referral::where('quotation_id', $quotation->id)->first();
        if ($referral) {
            $referral->update([
                'commission_pct' => $commissionPct ?: $referral->commission_tier_pct,
            ]);
        }

        return $order;
    }

    /**
     * Supersede (§3): the SAME order re-points at revision Rn and takes its
     * terms — agreed total, deposit, plan snapshot — then the timeline records
     * "Revised to R2 — <reason> — by <user>". The paid-to-date cache is never
     * written here (PaymentObserver stays its only writer).
     */
    public static function supersede(Order $order, QuotationRevision $revision, ?QuotationRevision $previous, Quotation $terms): Order
    {
        $order->update(array_merge(self::termsOf($terms), [
            'current_revision_id' => $revision->id,
            'revised_at' => now(),
        ]));

        $order->logActivity('order.revised', [
            'from_revision' => $previous?->number,
            'to_revision' => $revision->number,
            'change_reason' => $revision->change_reason,
        ]);

        return $order;
    }

    /**
     * The commercial terms an order carries from the revision it runs on. A
     * fixed-amount deposit is carried as the amount (pct left null) so
     * deposit_due_myr can never recompute a rounded pct back into a different
     * figure; a pct deposit keeps carrying the pct. The instalment / partner
     * schedule is snapshotted (null for a lump sum).
     *
     * @return array<string, mixed>
     */
    public static function termsOf(Quotation $terms): array
    {
        $plan = $terms->paymentPlan();

        return [
            'value_min_myr' => $terms->estimate_min_myr,
            'value_max_myr' => $terms->estimate_max_myr,
            'final_amount_myr' => $terms->finalAmount(),
            'deposit_pct' => $plan->isFixedDeposit() ? null : $terms->depositPct(),
            'deposit_amount_myr' => $plan->isFixedDeposit() ? $plan->depositAmount() : null,
            'payment_plan' => $plan->orderSnapshot(),
        ];
    }
}
