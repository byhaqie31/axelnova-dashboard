<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Observers\PaymentObserver;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Writes to the ledger. Every movement is a Payment row; PaymentObserver derives
 * the order/invoice paid caches from it. Nothing here touches a paid cache
 * directly.
 */
class PaymentService
{
    /**
     * Record a manual (admin-keyed) payment. gateway=manual, status=succeeded.
     *
     * @param  array  $data  invoice_id?, amount_myr, method, reference?, paid_at?, notes?
     */
    public static function record(Order $order, array $data, ?int $recordedBy = null): Payment
    {
        return DB::transaction(fn () => Payment::create([
            'payment_number' => ReferenceCodeGenerator::generate(DocumentType::Payment),
            'order_id' => $order->id,
            'invoice_id' => $data['invoice_id'] ?? null,
            'client_id' => $order->client_id,
            'recorded_by' => $recordedBy,
            'type' => 'payment',
            'gateway' => 'manual',
            'method' => $data['method'],
            'status' => 'succeeded',
            'amount_myr' => round((float) $data['amount_myr'], 2),
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
        ]));
    }

    /**
     * Refund (part of) a succeeded payment: a new negative row pointing back at
     * the original via parent_payment_id. The original stays succeeded.
     */
    public static function refund(Payment $payment, float $amount, ?string $notes = null, ?int $recordedBy = null): Payment
    {
        return DB::transaction(fn () => Payment::create([
            'payment_number' => ReferenceCodeGenerator::generate(DocumentType::Payment),
            'order_id' => $payment->order_id,
            'invoice_id' => $payment->invoice_id,
            'client_id' => $payment->client_id,
            'parent_payment_id' => $payment->id,
            'recorded_by' => $recordedBy,
            'type' => 'refund',
            'gateway' => 'manual',
            'method' => $payment->method->value,
            'status' => 'succeeded',
            'amount_myr' => -1 * round(abs($amount), 2),
            'notes' => $notes,
            'paid_at' => now(),
        ]));
    }

    /**
     * Move a payment's invoice allocation — link, re-link, or unlink (null).
     * Refund children follow their parent, the receipt's display link follows,
     * and BOTH invoices' caches end up recomputed: the observer handles the
     * new one when the payment saves; the old one is refreshed explicitly
     * because the observer can no longer see it.
     */
    public static function allocate(Payment $payment, ?Invoice $invoice): Payment
    {
        return DB::transaction(function () use ($payment, $invoice) {
            $previousInvoiceId = $payment->invoice_id;

            // Children first, quietly — so the observer recompute fired by the
            // parent's save already counts the refund rows on the new invoice.
            $payment->refunds()->withTrashed()->update(['invoice_id' => $invoice?->id]);

            $payment->invoice_id = $invoice?->id;
            $payment->save();

            // Receipts display their allocation; the PDF anchors the payment.
            $payment->receipt()->update(['invoice_id' => $invoice?->id]);

            if ($previousInvoiceId && $previousInvoiceId !== $invoice?->id) {
                $previous = Invoice::find($previousInvoiceId);
                if ($previous) {
                    PaymentObserver::recomputeInvoice($previous);
                }
            }

            $payment->logActivity($invoice ? 'payment.allocated' : 'payment.unallocated', [
                'invoice_id' => $invoice?->id,
                'previous_invoice_id' => $previousInvoiceId,
            ]);

            return $payment;
        });
    }

    /**
     * How much of a payment can still be refunded: its amount net of refunds
     * already booked against it (refund rows carry negative amounts).
     */
    public static function refundableMyr(Payment $payment): float
    {
        $alreadyRefunded = (float) $payment->refunds()
            ->where('status', PaymentStatus::Succeeded)
            ->sum('amount_myr'); // negative

        return round(max(0, (float) $payment->amount_myr + $alreadyRefunded), 2);
    }
}
