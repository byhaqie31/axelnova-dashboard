<?php

namespace Tests\Unit\Quoting;

use App\Services\Quoting\CarePlanBoundary;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * §3.5 / acceptance test 4 — the Care Plan boundary on supersede: the old
 * period ends at the last month already covered and the new one starts the
 * month after, so no month is billed twice and none is skipped.
 */
class CarePlanBoundaryTest extends TestCase
{
    private function d(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    /** M Automobile: R1 folded care into instalments from Nov 2026; nothing paid yet; superseded 11 Oct 2026. */
    public function test_nothing_covered_yet_the_new_plan_starts_where_the_old_was_due_to(): void
    {
        $b = CarePlanBoundary::at($this->d('2026-11-01'), null, $this->d('2026-10-11'));

        $this->assertSame('2026-10-31', $b['old_ends_on']->toDateString(), 'the old period closes empty');
        $this->assertSame('2026-11-01', $b['new_starts_on']->toDateString());
        $this->assertTrue($b['old_ends_on']->addDay()->equalTo($b['new_starts_on']), 'no gap');
    }

    public function test_covered_months_end_the_old_period_and_the_new_starts_the_month_after(): void
    {
        // Instalments 1 and 2 (Nov, Dec 2026) paid; superseded 5 Jan 2027.
        $b = CarePlanBoundary::at($this->d('2026-11-01'), $this->d('2026-12-20'), $this->d('2027-01-05'));

        $this->assertSame('2026-12-31', $b['old_ends_on']->toDateString());
        $this->assertSame('2027-01-01', $b['new_starts_on']->toDateString());
        $this->assertTrue($b['old_ends_on']->addDay()->equalTo($b['new_starts_on']));
    }

    public function test_months_prepaid_beyond_the_supersede_date_are_not_billed_again(): void
    {
        // Care paid through March 2027, superseded in January.
        $b = CarePlanBoundary::at($this->d('2026-11-01'), $this->d('2027-03-01'), $this->d('2027-01-15'));

        $this->assertSame('2027-03-31', $b['old_ends_on']->toDateString());
        $this->assertSame('2027-04-01', $b['new_starts_on']->toDateString());
    }

    public function test_care_added_by_a_revision_starts_the_month_after_the_supersede(): void
    {
        $b = CarePlanBoundary::at(null, null, $this->d('2026-10-11'));

        $this->assertNull($b['old_ends_on']);
        $this->assertSame('2026-11-01', $b['new_starts_on']->toDateString());
    }

    public function test_year_end_rolls_over(): void
    {
        $b = CarePlanBoundary::at($this->d('2026-06-01'), $this->d('2026-12-01'), $this->d('2026-12-18'));

        $this->assertSame('2027-01-01', $b['new_starts_on']->toDateString());
    }
}
