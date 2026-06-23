<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Mints the next code in the AXN document family — AXN{TYPE}-{YYYY}-{NNNN}
 * (e.g. AXNQ-2026-0012). The type letter fuses into the prefix, leaving a clean
 * numeric tail.
 *
 * The single source of truth for document identifiers. Each type keeps its own
 * yearly counter, reset every year, and the next sequence is read under a
 * row lock inside a transaction so concurrent mints can never collide.
 */
class ReferenceCodeGenerator
{
    public static function generate(DocumentType $type, ?int $year = null): string
    {
        // now() resolves in APP_TIMEZONE (Asia/Kuala_Lumpur), so the yearly
        // rollover flips at local midnight.
        $year ??= now()->year;

        $table = $type->table();
        $column = $type->column();

        // Invoices are on the roadmap; their table isn't provisioned yet. Fail
        // loudly rather than mint a code into a missing column.
        if (! Schema::hasTable($table)) {
            throw new RuntimeException(
                "Cannot mint {$type->name} code: '{$table}' table not yet provisioned.",
            );
        }

        // Type letter fuses into the prefix: AXNQ- / AXNO- / AXNI-.
        $prefix = sprintf('AXN%s-%d-', $type->value, $year);

        return DB::transaction(function () use ($table, $column, $prefix) {
            // Raw table query deliberately includes soft-deleted rows so a
            // sequence is never reused after a delete. Same prefix + fixed
            // zero-padded width means lexical desc == numeric desc, and the
            // trailing 4 digits are the sequence.
            $last = DB::table($table)
                ->where($column, 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc($column)
                ->value($column);

            $next = $last ? ((int) substr($last, -4)) + 1 : 1;

            return $prefix.sprintf('%04d', $next);
        });
    }
}
