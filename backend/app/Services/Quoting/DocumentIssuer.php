<?php

namespace App\Services\Quoting;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues invoices and receipts for an order: assigns a derived, atomic number,
 * freezes the DocumentData snapshot, and persists it. The PDF is never stored —
 * it renders on demand from the frozen `payload`.
 */
class DocumentIssuer
{
    private const PREFIX = ['invoice' => 'INV', 'receipt' => 'RCP'];

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
            $number = self::nextNumber($order, 'invoice');

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
            $number = self::nextNumber($order, 'receipt');

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

    /** Add a payment to the order's running paid total, clamped to the agreed total. */
    private static function accruePayment(Order $order, float $amount): void
    {
        $final = (float) $order->final_amount_myr;
        $paid = (float) $order->amount_paid_myr + $amount;
        $order->update([
            'amount_paid_myr' => $final > 0 ? min($paid, $final) : max($paid, 0),
        ]);
    }

    /**
     * Derived number: e.g. INV-AXNQ-2026-0011. A second document of the same type
     * for the same order gets a -2, -3 … suffix. Locked for the transaction.
     */
    private static function nextNumber(Order $order, string $type): string
    {
        $prefix = self::PREFIX[$type];
        $base = $order->quotation?->reference_code ?? $order->order_number;
        $root = "{$prefix}-{$base}";

        $taken = $type === 'invoice'
            ? Invoice::withTrashed()->where('invoice_number', 'like', "{$root}%")->lockForUpdate()->pluck('invoice_number')->all()
            : Receipt::withTrashed()->where('receipt_number', 'like', "{$root}%")->lockForUpdate()->pluck('receipt_number')->all();

        if (! in_array($root, $taken, true)) {
            return $root;
        }

        $n = 2;
        while (in_array("{$root}-{$n}", $taken, true)) {
            $n++;
        }

        return "{$root}-{$n}";
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
