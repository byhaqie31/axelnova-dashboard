<?php

namespace App\Services\Quoting;

use App\Models\Document;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues an invoice or receipt for an order: assigns a derived, atomic number,
 * freezes the DocumentData snapshot, and persists it. The PDF is never stored —
 * it renders on demand from the frozen `payload`.
 */
class DocumentIssuer
{
    private const PREFIX = ['invoice' => 'INV', 'receipt' => 'RCP'];

    /**
     * @param  array  $input  number override, layout, amountPaid, paymentRef,
     *                        paymentMethod, statusLabel, notes, payload override.
     */
    public static function issue(Order $order, string $type, array $input = []): Document
    {
        if (! isset(self::PREFIX[$type])) {
            throw new \InvalidArgumentException("Unknown document type: {$type}");
        }

        return DB::transaction(function () use ($order, $type, $input) {
            $number = self::nextNumber($order, $type);

            $payload = DocumentMapper::forOrder($order, $type, array_merge($input, [
                'number' => $number,
                'issued' => $input['issued'] ?? now()->format('d F Y'),
            ]));

            $total = self::payloadTotal($payload);

            return Document::create([
                'order_id' => $order->id,
                'type' => $type,
                'number' => $number,
                'public_token' => Str::random(48),
                'payload' => $payload,
                'amount_total' => $total,
                'amount_paid' => isset($input['amountPaid']) ? (float) $input['amountPaid'] : null,
                'payment_ref' => $input['paymentRef'] ?? null,
                'payment_method' => $input['paymentMethod'] ?? null,
                'status' => $input['status'] ?? 'issued',
                'issued_at' => now(),
            ]);
        });
    }

    /**
     * Derived number: e.g. INV-AXN-2026-0011. A second document of the same type
     * for the same order gets a -2, -3 … suffix. Locked for the transaction.
     */
    private static function nextNumber(Order $order, string $type): string
    {
        $prefix = self::PREFIX[$type];
        $base = $order->quotation?->reference_code ?? $order->order_number;
        $root = "{$prefix}-{$base}";

        $taken = Document::withTrashed()
            ->where('number', 'like', "{$root}%")
            ->lockForUpdate()
            ->pluck('number')
            ->all();

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
