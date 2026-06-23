<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Migrate existing identifiers into the unified AXN document family:
 *   quotations  AXN-2026-0011  →  AXN-Q-2026-0011
 *   orders      ORD-2026-0002  →  AXN-O-2026-0002
 *
 * Year-agnostic (regex), so any prior-year rows convert too. Idempotent: only
 * the OLD shape is matched, so re-running after conversion touches nothing.
 * Per-type sequences are preserved exactly — only the prefix changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $quotations = DB::update(<<<'SQL'
                UPDATE quotations
                SET reference_code = REGEXP_REPLACE(reference_code, '^AXN-([0-9]{4})-([0-9]{4})$', 'AXN-Q-$1-$2')
                WHERE reference_code REGEXP '^AXN-[0-9]{4}-[0-9]{4}$'
            SQL);

            $orders = DB::update(<<<'SQL'
                UPDATE orders
                SET order_number = REGEXP_REPLACE(order_number, '^ORD-([0-9]{4})-([0-9]{4})$', 'AXN-O-$1-$2')
                WHERE order_number REGEXP '^ORD-[0-9]{4}-[0-9]{4}$'
            SQL);

            $msg = "[AXN backfill] quotations converted: {$quotations}, orders converted: {$orders}";
            Log::info($msg);
            echo '  '.$msg.PHP_EOL;
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::update(<<<'SQL'
                UPDATE quotations
                SET reference_code = REGEXP_REPLACE(reference_code, '^AXN-Q-([0-9]{4})-([0-9]{4})$', 'AXN-$1-$2')
                WHERE reference_code REGEXP '^AXN-Q-[0-9]{4}-[0-9]{4}$'
            SQL);

            DB::update(<<<'SQL'
                UPDATE orders
                SET order_number = REGEXP_REPLACE(order_number, '^AXN-O-([0-9]{4})-([0-9]{4})$', 'ORD-$1-$2')
                WHERE order_number REGEXP '^AXN-O-[0-9]{4}-[0-9]{4}$'
            SQL);
        });
    }
};
