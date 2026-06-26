<?php

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Receipt;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Convert legacy derived money into real ledger rows, then let PaymentObserver
     * recompute the caches from those rows. Order matters: a payment per existing
     * receipt first (linked back), then one catch-up row for any paid balance not
     * covered by a receipt.
     *
     * Idempotent: receipts already linked are skipped, and the catch-up gap is
     * measured against the current ledger sum — so a re-run adds nothing.
     */
    public function up(): void
    {
        Order::with(['receipts', 'quotation'])->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                // Capture the legacy figure before any payment fires the observer;
                // our in-memory instance is never reloaded inside this loop.
                $legacyPaid = (float) $order->amount_paid_myr;

                // 1. One payment per existing (non-void) receipt, linked back.
                foreach ($order->receipts as $receipt) {
                    if ($receipt->payment_id || $receipt->status === 'void') {
                        continue;
                    }

                    $payment = Payment::create([
                        'payment_number' => ReferenceCodeGenerator::generate(DocumentType::Payment),
                        'order_id' => $order->id,
                        'invoice_id' => $receipt->invoice_id,
                        'client_id' => $order->client_id,
                        'type' => 'payment',
                        'gateway' => 'manual',
                        'method' => PaymentMethod::tryFrom((string) $receipt->payment_method)?->value ?? 'other',
                        'status' => 'succeeded',
                        'amount_myr' => $receipt->amount,
                        'reference' => $receipt->payment_ref,
                        'paid_at' => $receipt->issued_at,
                        'notes' => 'Backfilled from receipt '.$receipt->receipt_number,
                    ]);

                    $receipt->forceFill(['payment_id' => $payment->id])->saveQuietly();
                }

                // 2. Any legacy paid amount not yet represented in the ledger →
                //    one catch-up row. Measured against the live ledger sum so a
                //    second run (receipts already linked, catch-up already there)
                //    computes a zero gap and inserts nothing.
                $ledgered = (float) $order->payments()->succeeded()->sum('amount_myr');
                $gap = $legacyPaid - $ledgered;

                if ($gap > 0.009) {
                    Payment::create([
                        'payment_number' => ReferenceCodeGenerator::generate(DocumentType::Payment),
                        'order_id' => $order->id,
                        'client_id' => $order->client_id,
                        'type' => 'payment',
                        'gateway' => 'manual',
                        'method' => 'other',
                        'status' => 'succeeded',
                        'amount_myr' => $gap,
                        'paid_at' => $order->updated_at,
                        'notes' => 'Backfilled from legacy amount_paid_myr (unreceipted balance)',
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Drop the backfilled rows WITHOUT firing the observer, so the legacy
        // `amount_paid_myr` caches (which up() reproduced) are left intact.
        Payment::withoutEvents(function () {
            $ids = Payment::withTrashed()
                ->where('notes', 'like', 'Backfilled%')
                ->pluck('id');

            Receipt::whereIn('payment_id', $ids)->update(['payment_id' => null]);
            Payment::withTrashed()->whereIn('id', $ids)->forceDelete();
        });
    }
};
