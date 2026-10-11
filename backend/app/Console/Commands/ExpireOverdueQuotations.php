<?php

namespace App\Console\Commands;

use App\Models\Quotation;
use Illuminate\Console\Command;

/**
 * Expire every sent quotation past its validity. Scheduled hourly
 * (routes/console.php); the lazy on-read expiry in the admin list stays as a
 * safety net for whenever the scheduler isn't running.
 */
class ExpireOverdueQuotations extends Command
{
    protected $signature = 'quotations:expire-overdue';

    protected $description = 'Expire sent quotations that are past their validity date';

    public function handle(): int
    {
        Quotation::expireOverdue();

        return self::SUCCESS;
    }
}
