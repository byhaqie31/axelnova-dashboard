<?php

namespace App\Support;

/**
 * The AXN document family — one branded prefix, a one-letter type, then a
 * per-type yearly sequence: AXN-{TYPE}-{YYYY}-{NNNN}.
 *
 * Each case knows the table + column its sequence lives in, so the generator
 * stays the single source of truth for minting codes.
 */
enum DocumentType: string
{
    case Quotation = 'Q';
    case Order = 'O';
    case Invoice = 'I';

    /** The table whose column carries this type's codes. */
    public function table(): string
    {
        return match ($this) {
            self::Quotation => 'quotations',
            self::Order => 'orders',
            self::Invoice => 'invoices',
        };
    }

    /** The column the code is stored in (also scanned for the max sequence). */
    public function column(): string
    {
        return match ($this) {
            self::Quotation => 'reference_code',
            self::Order => 'order_number',
            self::Invoice => 'invoice_number',
        };
    }
}
