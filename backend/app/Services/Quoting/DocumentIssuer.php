<?php

namespace App\Services\Quoting;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Receipt;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues invoices and receipts for an order: mints an atomic AXN-family number
 * (AXNI / AXNR via ReferenceCodeGenerator, each its own yearly counter), freezes
 * the DocumentData snapshot, and persists it. The PDF is never stored — it
 * renders on demand from the frozen `payload`.
 */
class DocumentIssuer
{
    /**
     * Issue an invoice (deposit / partial / final). A recorded payment accrues
     * onto the order's running paid total and stamps `paid_at` when fully paid.
     *
     * @param  array  $input  invoiceType, amountPaid, paymentRef, paymentMethod,
     *                        status, issued, payload override.
     */
    public static function issueInvoice(Order $order, array $input = []): Invoice
    {
        return DB::transaction(function () use ($order, $input) {
            $number = ReferenceCodeGenerator::generate(DocumentType::Invoice);

            $payload = DocumentMapper::forOrder($order, 'invoice', array_merge($input, [
                'number' => $number,
                'issued' => $input['issued'] ?? now()->format('d F Y'),
            ]));

            $amountPaid = isset($input['amountPaid']) ? (float) $input['amountPaid'] : null;
            $status = $input['status'] ?? 'issued';

            $invoice = Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => $number,
                'public_token' => Str::random(48),
                'type' => $input['invoiceType'] ?? 'deposit',
                'payload' => $payload,
                'amount_total' => self::payloadTotal($payload),
                'amount_paid' => $amountPaid,
                'payment_ref' => $input['paymentRef'] ?? null,
                'payment_method' => $input['paymentMethod'] ?? null,
                'status' => $status,
                'issued_at' => now(),
                'due_at' => $input['dueAt'] ?? now()->addDays(14)->toDateString(),
                'paid_at' => $status === 'paid' ? now() : null,
            ]);

            if ($amountPaid !== null && $amountPaid > 0) {
                self::accruePayment($order, $amountPaid);
            }

            return $invoice;
        });
    }

    /**
     * Issue a receipt confirming a settled payment, optionally tied to the
     * invoice it settles.
     *
     * @param  array  $input  invoice_id, amountPaid, paymentRef, paymentMethod,
     *                        issued, payload override.
     */
    public static function issueReceipt(Order $order, array $input = []): Receipt
    {
        return DB::transaction(function () use ($order, $input) {
            $number = ReferenceCodeGenerator::generate(DocumentType::Receipt);

            $payload = DocumentMapper::forOrder($order, 'receipt', array_merge($input, [
                'number' => $number,
                'issued' => $input['issued'] ?? now()->format('d F Y'),
            ]));

            return Receipt::create([
                'order_id' => $order->id,
                'invoice_id' => $input['invoice_id'] ?? null,
                'receipt_number' => $number,
                'public_token' => Str::random(48),
                'payload' => $payload,
                'amount' => isset($input['amountPaid']) ? (float) $input['amountPaid'] : self::payloadTotal($payload),
                'payment_ref' => $input['paymentRef'] ?? null,
                'payment_method' => $input['paymentMethod'] ?? null,
                'status' => 'issued',
                'issued_at' => now(),
            ]);
        });
    }

    /**
     * Issue a receipt for a succeeded payment — the ledger-anchored path. Works
     * even when the payment has no invoice (a deposit paid before any invoice was
     * issued still gets a receipt). The frozen snapshot is built from the
     * payment → its invoice (if any) → order, with amount / ref / method taken
     * from the payment itself.
     */
    public static function receiptForPayment(Payment $payment): Receipt
    {
        return DB::transaction(function () use ($payment) {
            $payment->loadMissing('order.quotation');
            $order = $payment->order;
            $number = ReferenceCodeGenerator::generate(DocumentType::Receipt);

            $payload = DocumentMapper::forOrder($order, 'receipt', [
                'number' => $number,
                'issued' => now()->format('d F Y'),
                'amountPaid' => (float) $payment->amount_myr,
                'paymentRef' => $payment->reference,
                'paymentMethod' => $payment->method->value,
            ]);

            return Receipt::create([
                'order_id' => $order->id,
                'invoice_id' => $payment->invoice_id,
                'payment_id' => $payment->id,
                'receipt_number' => $number,
                'public_token' => Str::random(48),
                'payload' => $payload,
                'amount' => (float) $payment->amount_myr,
                'payment_ref' => $payment->reference,
                'payment_method' => $payment->method->value,
                'status' => 'issued',
                'issued_at' => now(),
            ]);
        });
    }

    /** Add a payment to the order's running paid total, clamped to the agreed total. */
    private static function accruePayment(Order $order, float $amount): void
    {
        $final = (float) $order->final_amount_myr;
        $paid = (float) $order->amount_paid_myr + $amount;
        $order->update([
            'amount_paid_myr' => $final > 0 ? min($paid, $final) : max($paid, 0),
        ]);
    }

    /** Total = the document's "total/red" summary row, falling back to subtotal. */
    private static function payloadTotal(array $payload): float
    {
        $rows = $payload['summary']['rows'] ?? [];
        foreach ($rows as $row) {
            if (! empty($row['total'])) {
                return (float) ($row['price'] ?? 0);
            }
        }

        return (float) ($rows[0]['price'] ?? 0);
    }
}
