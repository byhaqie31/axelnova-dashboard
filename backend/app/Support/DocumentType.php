<?php

namespace App\Support;

/**
 * The AXN document family — one branded prefix with the type letter fused in,
 * then a per-type yearly sequence: AXN{TYPE}-{YYYY}-{NNNN} (e.g. AXNQ-2026-0012).
 *
 * Each case knows the table + column its sequence lives in, so the generator
 * stays the single source of truth for minting codes.
 */
enum DocumentType: string
{
    case Quotation = 'Q';
    case Order = 'O';
    case Invoice = 'I';
    case Receipt = 'R';
    case Payment = 'P';

    /** The table whose column carries this type's codes. */
    public function table(): string
    {
        return match ($this) {
            self::Quotation => 'quotations',
            self::Order => 'orders',
            self::Invoice => 'invoices',
            self::Receipt => 'receipts',
            self::Payment => 'payments',
        };
    }

    /** The column the code is stored in (also scanned for the max sequence). */
    public function column(): string
    {
        return match ($this) {
            self::Quotation => 'reference_code',
            self::Order => 'order_number',
            self::Invoice => 'invoice_number',
            self::Receipt => 'receipt_number',
            self::Payment => 'payment_number',
        };
    }
}
