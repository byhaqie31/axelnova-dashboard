<?php

namespace App\Services\Quoting;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Receipt;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Support\Arr;
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
     * Issue-form fields kept on the invoice for re-editing. Editing merges new
     * values over these and re-runs DocumentMapper — the payload is always a
     * pure function of (order, inputs, number, issued).
     */
    private const INPUT_KEYS = [
        'invoiceType', 'amount', 'amountPaid', 'paymentRef', 'paymentMethod',
        'discountType', 'discountValue', 'discountLabel',
        'promoCode', 'promoType', 'promoValue', 'notes', 'dueAt',
    ];

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

            $status = $input['status'] ?? 'issued';

            $invoice = Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => $number,
                'public_token' => Str::random(48),
                'type' => $input['invoiceType'] ?? 'deposit',
                'payload' => $payload,
                'inputs' => self::cleanInputs($input),
                'amount_total' => self::payloadTotal($payload),
                'amount_paid' => null,
                'payment_ref' => $input['paymentRef'] ?? null,
                'payment_method' => $input['paymentMethod'] ?? null,
                'status' => $status,
                'issued_at' => now(),
                'due_at' => $input['dueAt'] ?? now()->addDays(14)->toDateString(),
                'paid_at' => $status === 'paid' ? now() : null,
            ]);

            return $invoice;
        });
    }

    /**
     * Re-edit an issued invoice in place: merge the new form fields over the
     * stored issue inputs, re-run DocumentMapper, and re-freeze the payload —
     * same AXNI number, same public token, same issued date. The caller is
     * responsible for the amounts-locked / void guards.
     *
     * Keys PRESENT in $input override the stored value (a present null clears
     * it, e.g. removing a discount); absent keys keep the stored value.
     */
    public static function updateInvoice(Invoice $invoice, array $input): Invoice
    {
        return DB::transaction(function () use ($invoice, $input) {
            $invoice->loadMissing('order.quotation');

            $inputs = array_replace(
                self::effectiveInputs($invoice),
                Arr::only($input, self::INPUT_KEYS),
            );

            $payload = DocumentMapper::forOrder($invoice->order, 'invoice', array_merge($inputs, [
                'number' => $invoice->invoice_number,
                // Keep the frozen issue date — editing is a correction, not a re-issue.
                'issued' => $invoice->payload['issued'] ?? $invoice->issued_at?->format('d F Y'),
            ]));

            $invoice->update([
                'payload' => $payload,
                'inputs' => self::cleanInputs($inputs),
                'amount_total' => self::payloadTotal($payload),
                'type' => $inputs['invoiceType'] ?? $invoice->type,
                'due_at' => $inputs['dueAt'] ?? $invoice->due_at,
            ]);

            return $invoice->refresh();
        });
    }

    /**
     * The issue-form fields to pre-fill the edit form with — stored inputs, or
     * the legacy fallback for invoices issued before `inputs` existed.
     */
    public static function effectiveInputs(Invoice $invoice): array
    {
        return $invoice->inputs ?? self::legacyInputs($invoice);
    }

    /**
     * Best-effort inputs for invoices issued before `inputs` existed: the net
     * total as the billed amount (discounts were already applied into it) and
     * the payload notes flattened back to text. Good enough to re-edit — the
     * live preview shows the regenerated document before anything is saved.
     */
    private static function legacyInputs(Invoice $invoice): array
    {
        $notes = $invoice->payload['notes'] ?? null;
        if (is_array($notes)) {
            $notes = implode("\n", array_map(
                fn ($n) => trim(($n['label'] ?? '').' '.($n['text'] ?? '')),
                $notes,
            ));
        }

        return array_filter([
            'invoiceType' => $invoice->type,
            'amount' => (float) $invoice->amount_total,
            'notes' => is_string($notes) && trim($notes) !== '' ? $notes : null,
        ], fn ($v) => $v !== null);
    }

    /** Whitelist + drop empties — what gets persisted to `invoices.inputs`. */
    private static function cleanInputs(array $input): array
    {
        return array_filter(
            Arr::only($input, self::INPUT_KEYS),
            fn ($v) => $v !== null && $v !== '',
        );
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
                'amount' => (float) $payment->amount_myr,
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
