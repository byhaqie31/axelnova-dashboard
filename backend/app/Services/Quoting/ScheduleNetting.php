<?php

namespace App\Services\Quoting;

/**
 * Nets a revised payment schedule against what the client has already paid
 * (docs/global/QUOTATION-REVISIONS.md §4.3). Pure — no I/O.
 *
 * Let P = paid to date, T = the new revision's total, S = its scheduled amounts
 * in order (up-front first). Walk S consuming P:
 *   • while P ≥ s_i — s_i is SETTLED by prior payment, P −= s_i;
 *   • the first item P only partly covers becomes an ADJUSTMENT of s_i − P;
 *   • every later item is DUE as normal.
 * If P > T the order is OVERPAID (credit note, handled by hand — v1): every item
 * is settled, nothing is adjusted, and the overpayment is reported.
 *
 * Worked example (M Automobile, AXNQ-2026-0017): P = 2,700, S = [4,320, 4,320,
 * 2,160] → adjustment 1,620, then 4,320 and 2,160 due.
 */
final class ScheduleNetting
{
    public const SETTLED = 'settled';

    public const ADJUSTMENT = 'adjustment';

    public const DUE = 'due';

    /**
     * @param  list<array{key: string, label: string, amount: float, date?: ?string}>  $items  PaymentPlan::scheduleItems()
     * @return array{paid_myr: float, total_myr: float, overpaid_myr: float, adjustment_myr: float, items: list<array{key: string, label: string, amount: float, date: ?string, state: string, covered_myr: float, due_myr: float}>}
     */
    public static function net(float $paid, float $total, array $items): array
    {
        $paid = round(max($paid, 0), 2);
        $total = round(max($total, 0), 2);
        $overpaid = $paid - $total > 0.004 ? round($paid - $total, 2) : 0.0;

        $remaining = $paid;
        $adjustment = 0.0;
        $out = [];
        foreach ($items as $item) {
            $amount = round((float) $item['amount'], 2);

            if ($overpaid > 0 || $remaining - $amount > -0.005) {
                $state = self::SETTLED;
                $covered = $amount;
            } elseif ($remaining > 0.004) {
                $state = self::ADJUSTMENT;
                $covered = $remaining;
                $adjustment = round($amount - $remaining, 2);
            } else {
                $state = self::DUE;
                $covered = 0.0;
            }
            $remaining = round(max($remaining - $covered, 0), 2);

            $out[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'amount' => $amount,
                'date' => $item['date'] ?? null,
                'state' => $state,
                'covered_myr' => round($covered, 2),
                'due_myr' => round($amount - $covered, 2),
            ];
        }

        return [
            'paid_myr' => $paid,
            'total_myr' => $total,
            'overpaid_myr' => $overpaid,
            'adjustment_myr' => $adjustment,
            'items' => $out,
        ];
    }
}
