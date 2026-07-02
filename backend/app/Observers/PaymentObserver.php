<?php

namespace App\Observers;

use App\Models\Payment;

/**
 * The single writer of the derived paid caches (`orders.amount_paid_myr`,
 * `invoices.amount_paid` + `invoices.status`). Every time a payment is saved,
 * deleted, or restored, the affected order and invoice are recomputed straight
 * from the ledger — `SUM(amount_myr)` over succeeded rows, refunds subtracting.
 *
 * No endpoint edits a paid amount directly; that was the old drift bug. The
 * observer is on Payment, so the `saveQuietly()` writes to Order / Invoice here
 * do not recurse.
 */
class PaymentObserver
{
    public function saved(Payment $payment): void
    {
        $this->recompute($payment);
    }

    public function deleted(Payment $payment): void
    {
        $this->recompute($payment);
    }

    public function restored(Payment $payment): void
    {
        $this->recompute($payment);
    }

    private function recompute(Payment $payment): void
    {
        $order = $payment->order()->first();
        if ($order) {
            // Signed sum: refund rows (negative) subtract. Clamp at 0 so a
            // refund can never drive the cache negative.
            $paid = (float) $order->payments()->succeeded()->sum('amount_myr');
            $order->forceFill(['amount_paid_myr' => max(0, $paid)])->saveQuietly();

            // A referral earns once the deposit lands, and stops if fully refunded.
            if ($order->quotation_id) {
                $referral = \App\Models\Referral::where('quotation_id', $order->quotation_id)->first();
                if ($referral) {
                    if ($paid > 0 && $referral->status === 'draft') {
                        $referral->update(['status' => 'converted']);
                        $referral->logActivity('referral.converted', ['order_id' => $order->id]);
                    } elseif ($paid <= 0 && $referral->status === 'converted') {
                        $referral->update(['status' => 'draft']);
                    }
                }
            }
        }

        $invoice = $payment->invoice()->first();
        // `void` is a manual terminal state — never auto-flip it back.
        if ($invoice && $invoice->status !== 'void') {
            $paid = (float) $invoice->payments()->succeeded()->sum('amount_myr');
            $total = (float) $invoice->amount_total;
            $fullyPaid = $total > 0 && $paid >= $total;

            $invoice->forceFill([
                'amount_paid' => max(0, $paid),
                'status' => $fullyPaid ? 'paid' : 'issued',
                'paid_at' => $fullyPaid ? ($invoice->paid_at ?? now()) : null,
            ])->saveQuietly();
        }
    }
}
