<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copy the combined `documents` rows into the new split tables — NON-destructive:
     * the `documents` table is left intact as a fallback until verified in prod.
     * Receipts link to the first invoice of their order (best-effort); existing
     * invoices are typed final when fully paid, otherwise deposit.
     */
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        $docs = DB::table('documents')->orderBy('id')->get();
        $firstInvoiceByOrder = [];

        // Invoices first, so receipts can reference them.
        foreach ($docs as $doc) {
            if ($doc->type !== 'invoice') {
                continue;
            }

            $paidInFull = $doc->amount_paid !== null && (float) $doc->amount_paid >= (float) $doc->amount_total;

            $id = DB::table('invoices')->insertGetId([
                'order_id' => $doc->order_id,
                'invoice_number' => $doc->number,
                'public_token' => $doc->public_token,
                'type' => $paidInFull ? 'final' : 'deposit',
                'payload' => $doc->payload,
                'amount_total' => $doc->amount_total,
                'amount_paid' => $doc->amount_paid,
                'payment_ref' => $doc->payment_ref,
                'payment_method' => $doc->payment_method,
                'status' => $doc->status,
                'issued_at' => $doc->issued_at,
                'paid_at' => $doc->status === 'paid' ? $doc->issued_at : null,
                'created_at' => $doc->created_at,
                'updated_at' => $doc->updated_at,
                'deleted_at' => $doc->deleted_at,
            ]);

            $firstInvoiceByOrder[$doc->order_id] ??= $id;
        }

        foreach ($docs as $doc) {
            if ($doc->type !== 'receipt') {
                continue;
            }

            DB::table('receipts')->insert([
                'order_id' => $doc->order_id,
                'invoice_id' => $firstInvoiceByOrder[$doc->order_id] ?? null,
                'receipt_number' => $doc->number,
                'public_token' => $doc->public_token,
                'payload' => $doc->payload,
                'amount' => $doc->amount_paid ?? $doc->amount_total,
                'payment_ref' => $doc->payment_ref,
                'payment_method' => $doc->payment_method,
                'status' => $doc->status === 'void' ? 'void' : 'issued',
                'issued_at' => $doc->issued_at,
                'created_at' => $doc->created_at,
                'updated_at' => $doc->updated_at,
                'deleted_at' => $doc->deleted_at,
            ]);
        }
    }

    public function down(): void
    {
        // Receipts reference invoices — clear them first.
        DB::table('receipts')->delete();
        DB::table('invoices')->delete();
    }
};
