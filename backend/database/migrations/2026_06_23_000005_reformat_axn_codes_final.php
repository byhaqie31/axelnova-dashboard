<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lock the AXN family to its production-final form — the type letter fuses into
 * the prefix: AXN-Q-2026-0011 → AXNQ-2026-0011, AXN-O-… → AXNO-…
 *
 * Each UPDATE matches only one specific old shape (current Option-1 form, a
 * year-fused variant, and the original legacy/ORD shape), so the set is safe to
 * run once and idempotent on re-run. Final values (AXNQ-/AXNO-) match none of
 * the old patterns. Per-type sequences are preserved — only the prefix changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $converted = 0;

            // quotations → AXNQ-YYYY-NNNN
            $converted += DB::update(<<<'SQL'
                UPDATE quotations SET reference_code = REGEXP_REPLACE(reference_code, '^AXN-Q-([0-9]{4})-([0-9]{4})$', 'AXNQ-$1-$2')
                WHERE reference_code REGEXP '^AXN-Q-[0-9]{4}-[0-9]{4}$'
            SQL);
            $converted += DB::update(<<<'SQL'
                UPDATE quotations SET reference_code = REGEXP_REPLACE(reference_code, '^AXN-([0-9]{4})-Q([0-9]{4})$', 'AXNQ-$1-$2')
                WHERE reference_code REGEXP '^AXN-[0-9]{4}-Q[0-9]{4}$'
            SQL);
            $converted += DB::update(<<<'SQL'
                UPDATE quotations SET reference_code = REGEXP_REPLACE(reference_code, '^AXN-([0-9]{4})-([0-9]{4})$', 'AXNQ-$1-$2')
                WHERE reference_code REGEXP '^AXN-[0-9]{4}-[0-9]{4}$'
            SQL);

            // orders → AXNO-YYYY-NNNN
            $converted += DB::update(<<<'SQL'
                UPDATE orders SET order_number = REGEXP_REPLACE(order_number, '^AXN-O-([0-9]{4})-([0-9]{4})$', 'AXNO-$1-$2')
                WHERE order_number REGEXP '^AXN-O-[0-9]{4}-[0-9]{4}$'
            SQL);
            $converted += DB::update(<<<'SQL'
                UPDATE orders SET order_number = REGEXP_REPLACE(order_number, '^AXN-([0-9]{4})-O([0-9]{4})$', 'AXNO-$1-$2')
                WHERE order_number REGEXP '^AXN-[0-9]{4}-O[0-9]{4}$'
            SQL);
            $converted += DB::update(<<<'SQL'
                UPDATE orders SET order_number = REGEXP_REPLACE(order_number, '^ORD-([0-9]{4})-([0-9]{4})$', 'AXNO-$1-$2')
                WHERE order_number REGEXP '^ORD-[0-9]{4}-[0-9]{4}$'
            SQL);

            // Fail loudly before production if any row escaped the patterns.
            $final = '^AXN[QOI]-[0-9]{4}-[0-9]{4}$';
            $badQ = DB::table('quotations')->where('reference_code', 'not regexp', $final)->count();
            $badO = DB::table('orders')->where('order_number', 'not regexp', $final)->count();

            if ($badQ > 0 || $badO > 0) {
                throw new RuntimeException("[AXN final reformat] unconverted rows — quotations: {$badQ}, orders: {$badO}");
            }

            $msg = "[AXN final reformat] converted {$converted} rows; all quotations + orders now match {$final}";
            Log::info($msg);
            echo '  '.$msg.PHP_EOL;
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::update(<<<'SQL'
                UPDATE quotations SET reference_code = REGEXP_REPLACE(reference_code, '^AXNQ-([0-9]{4})-([0-9]{4})$', 'AXN-Q-$1-$2')
                WHERE reference_code REGEXP '^AXNQ-[0-9]{4}-[0-9]{4}$'
            SQL);
            DB::update(<<<'SQL'
                UPDATE orders SET order_number = REGEXP_REPLACE(order_number, '^AXNO-([0-9]{4})-([0-9]{4})$', 'AXN-O-$1-$2')
                WHERE order_number REGEXP '^AXNO-[0-9]{4}-[0-9]{4}$'
            SQL);
        });
    }
};
