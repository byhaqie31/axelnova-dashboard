<?php

namespace App\Enums;

/**
 * Lifecycle of a ledger row. Only `succeeded` rows count toward the order /
 * invoice paid caches (see PaymentObserver). A refund is its own `succeeded`
 * row with a negative amount — the original stays `succeeded`, never flips.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
}
