<?php

namespace App\Enums;

/**
 * Where a payment was processed. `manual` covers bank transfer / cash recorded
 * by an admin; the gateways come online in the webhook phases (Billplz for MYR
 * FPX/DuitNow, Stripe for card/international).
 */
enum PaymentGateway: string
{
    case Stripe = 'stripe';
    case Billplz = 'billplz';
    case Manual = 'manual';
}
