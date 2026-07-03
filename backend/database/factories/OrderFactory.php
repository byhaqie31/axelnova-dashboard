<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Order;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_number' => 'AXNO-2001-'.fake()->unique()->numerify('####'),
            'quotation_id' => Quotation::factory(),
            'client_id' => Client::factory(),
            'value_min_myr' => 1500,
            'value_max_myr' => 2500,
            'final_amount_myr' => 2500,
            'deposit_pct' => 50,
            'amount_paid_myr' => 0,
            'status' => 'in_progress',
        ];
    }
}
