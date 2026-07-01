<?php

namespace App\Enums;

/**
 * How the money moved. Stored as a string + cast (not a MySQL enum) so adding a
 * method later is a code change, not an ALTER.
 */
enum PaymentMethod: string
{
    case Card = 'card';
    case Fpx = 'fpx';
    case Duitnow = 'duitnow';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Ewallet = 'ewallet';
    case Other = 'other';
}
