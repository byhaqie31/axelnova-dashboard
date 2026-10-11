<?php

namespace Tests\Unit\Mail;

use App\Support\Mail\EmailFormat;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Email values follow the design kit: whole ringgit drop the cents
 * ("RM 6,500"), real cents stay ("RM 1,234.50"); dates read "10 Nov 2026".
 */
class EmailFormatTest extends TestCase
{
    public function test_money_drops_zero_cents_and_keeps_real_cents(): void
    {
        $this->assertSame('RM 6,500', EmailFormat::money(6500));
        $this->assertSame('RM 6,500', EmailFormat::money('6500.00'));
        $this->assertSame('RM 1,234.50', EmailFormat::money(1234.5));
        $this->assertSame('RM 0', EmailFormat::money(null));
    }

    public function test_range_collapses_when_min_equals_max(): void
    {
        $this->assertSame('RM 5,000 to RM 8,000', EmailFormat::range(5000, 8000));
        $this->assertSame('RM 5,000', EmailFormat::range(5000, 5000));
    }

    public function test_dates_use_the_kit_format(): void
    {
        $this->assertSame('10 Nov 2026', EmailFormat::date(Carbon::parse('2026-11-10')));
        $this->assertSame('11 Oct 2026, 8:46 am', EmailFormat::dateTime(Carbon::parse('2026-10-11 08:46')));
        $this->assertNull(EmailFormat::date(null));
        $this->assertNull(EmailFormat::dateTime(null));
    }
}
