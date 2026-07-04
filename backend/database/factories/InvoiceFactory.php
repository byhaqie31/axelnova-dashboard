<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'invoice_number' => 'AXNI-2001-'.fake()->unique()->numerify('####'),
            'public_token' => Str::random(48),
            'type' => 'deposit',
            'payload' => [],
            'amount_total' => 1250,
            'status' => 'issued',
            'issued_at' => now(),
        ];
    }
}
