<?php

namespace App\Enums;

/**
 * A ledger row is either money in (`payment`) or money out (`refund`). Refunds
 * carry a negative `amount_myr` and point back at the original via
 * `parent_payment_id`, so `SUM(amount_myr)` nets out across the ledger.
 */
enum PaymentType: string
{
    case Payment = 'payment';
    case Refund = 'refund';
}
