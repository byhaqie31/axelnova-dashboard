<?php

namespace App\Services\Quoting;

use App\Models\Invoice;
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

        CarePlans::open($order, $revision, $terms->paymentPlan());

        return $order;
    }

    /**
     * Supersede (§3): the SAME order re-points at revision Rn and takes its terms
     * — agreed total, deposit, plan snapshot — then the money follows (§4):
     *
     *   1. unpaid issued invoices are VOIDED ("Superseded by R2"); a part-paid
     *      one is voided too, its payments staying on the ledger and counting
     *      toward paid-to-date; paid invoices are untouched; care_plan invoices
     *      bill a delivered month of a separate subscription and are left alone;
     *   2. the new schedule is NETTED against paid-to-date (ScheduleNetting):
     *      settled items need nothing, the first part-covered one becomes an
     *      ADJUSTMENT invoice for the difference, the rest are billed as normal
     *      when they fall due; P > T flags the order OVERPAID instead;
     *   3. Care Plan periods end at the last month covered and the new one (if
     *      any) starts the month after;
     *   4. the timeline records "Revised to R2 — <reason> — by <user>".
     *
     * The paid-to-date cache is never written here (PaymentObserver stays its
     * only writer). Runs inside QuotationRevisions::accept()'s transaction.
     */
    public static function supersede(Order $order, QuotationRevision $revision, ?QuotationRevision $previous, Quotation $terms): Order
    {
        // The plan the existing invoices billed — read before the terms move on.
        // Without a snapshot the order would fall back to the family row, which
        // already mirrors Rn, so a lump sum's old plan comes from Rn-1 itself.
        $oldPlan = $order->payment_plan !== null || $previous === null
            ? $order->paymentPlan()
            : $previous->asQuotation()->paymentPlan();

        $order->update(array_merge(self::termsOf($terms), [
            'current_revision_id' => $revision->id,
            'revised_at' => now(),
        ]));
        $newPlan = $order->paymentPlan();

        $voided = self::voidOpenInvoices($order, $revision);

        $paid = max(0.0, (float) $order->payments()->succeeded()->sum('amount_myr'));
        $netting = ScheduleNetting::net($paid, (float) $order->final_amount_myr, $newPlan->scheduleItems());

        $flags = [];
        if ($netting['overpaid_myr'] > 0) {
            $flags['overpaid_myr'] = $netting['overpaid_myr'];
        } elseif ($netting['adjustment_myr'] > 0) {
            $index = collect($netting['items'])->search(fn (array $item) => $item['state'] === ScheduleNetting::ADJUSTMENT);
            $item = $netting['items'][$index];
            $what = in_array($item['key'], ['deposit', 'phase:1'], true) ? $newPlan->upfrontLabel() : $item['label'];
            $from = $previous?->label() ?? 'R'.max($revision->number - 1, 1);

            $adjustment = DocumentIssuer::issueInvoice($order, [
                'invoiceType' => 'adjustment',
                'amount' => $netting['adjustment_myr'],
                'adjustmentLabel' => "{$what} top-up, {$from} → {$revision->label()}",
                'notes' => 'RM '.PaymentPlan::fmt($item['covered_myr'])." already paid under {$from} is carried into {$revision->label()}; this invoice tops {$item['label']} up to RM ".PaymentPlan::fmt($item['amount']).'.',
            ]);
            $netting['items'][$index]['invoice_id'] = $adjustment->id;
            $adjustment->logActivity('invoice.issued', [
                'invoice_number' => $adjustment->invoice_number,
                'order_number' => $order->order_number,
                'reason' => 'revision_adjustment',
            ]);
        }

        $careChange = CarePlans::supersede($order, $previous, $revision, $oldPlan, $newPlan);
        if ($careChange !== null) {
            $flags['care_plan_changed'] = $careChange;
        }

        $order->forceFill([
            'schedule_netting' => array_merge($netting, [
                'revision_id' => $revision->id,
                'revision' => $revision->number,
                'from_revision' => $previous?->number,
                'netted_at' => now()->toISOString(),
                'voided_invoices' => $voided,
            ]),
            'revision_flags' => $flags === [] ? null : $flags,
        ])->save();

        $order->logActivity('order.revised', [
            'from_revision' => $previous?->number,
            'to_revision' => $revision->number,
            'change_reason' => $revision->change_reason,
            'voided_invoices' => $voided,
            'adjustment_myr' => $netting['adjustment_myr'],
            'overpaid_myr' => $netting['overpaid_myr'],
        ]);

        return $order;
    }

    /**
     * §4.2 — void every issued invoice that isn't fully paid (care_plan months
     * excepted). Its number stays in the register, marked void with the reason;
     * a part-paid invoice's money stays on the ledger (paid-to-date) and is
     * netted into the new schedule.
     *
     * @return list<string> the voided invoice numbers
     */
    private static function voidOpenInvoices(Order $order, QuotationRevision $revision): array
    {
        $voided = [];
        $open = Invoice::where('order_id', $order->id)
            ->where('status', 'issued')
            ->where('type', '!=', 'care_plan')
            ->get();

        foreach ($open as $invoice) {
            $paid = (float) $invoice->payments()->succeeded()->sum('amount_myr');
            $invoice->forceFill([
                'status' => 'void',
                'void_reason' => $paid > 0
                    ? "Superseded by {$revision->label()} — RM ".PaymentPlan::fmt($paid).' paid on it is carried into the new schedule'
                    : "Superseded by {$revision->label()}",
            ])->save();
            $invoice->logActivity('invoice.voided', ['reason' => $invoice->void_reason]);
            $voided[] = $invoice->invoice_number;
        }

        return $voided;
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
            // A phased lump sum carries phase 1 as its fixed up-front amount.
            'deposit_pct' => $plan->isFixedDeposit() || $plan->isPhased() ? null : $terms->depositPct(),
            'deposit_amount_myr' => $plan->isFixedDeposit() || $plan->isPhased() ? $plan->depositAmount() : null,
            'payment_plan' => $plan->orderSnapshot(),
        ];
    }
}
