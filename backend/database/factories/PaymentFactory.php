<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 *
 * Ledger rows: pass `order_id`/`client_id` together (a payment's client should
 * match its order's client) — the observer recomputes caches on create.
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_number' => 'AXNP-2001-'.fake()->unique()->numerify('####'),
            'order_id' => Order::factory(),
            'client_id' => Client::factory(),
            'type' => 'payment',
            'gateway' => 'manual',
            'method' => 'bank_transfer',
            'status' => 'succeeded',
            'amount_myr' => 500,
            'paid_at' => now(),
        ];
    }

    /** A refund row reversing $payment — negative amount, linked to its original. */
    public function refundOf(Payment $payment, ?float $amount = null): static
    {
        return $this->state([
            'type' => 'refund',
            'order_id' => $payment->order_id,
            'invoice_id' => $payment->invoice_id,
            'client_id' => $payment->client_id,
            'parent_payment_id' => $payment->id,
            'amount_myr' => -abs($amount ?? (float) $payment->amount_myr),
        ]);
    }
}
