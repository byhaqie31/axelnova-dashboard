<?php

namespace App\Support;

use App\Models\QuoteRequest;
use Illuminate\Support\Facades\DB;

class ReferenceCodeGenerator
{
    public static function generate(): string
    {
        return DB::transaction(function () {
            $year = now()->year;

            $last = QuoteRequest::withTrashed()
                ->where('reference_code', 'like', "AXN-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc('reference_code')
                ->value('reference_code');

            $next = $last ? ((int) substr($last, -4)) + 1 : 1;

            return sprintf('AXN-%d-%04d', $year, $next);
        });
    }
}
