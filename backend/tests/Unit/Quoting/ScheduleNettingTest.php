<?php

namespace Tests\Unit\Quoting;

use App\Services\Quoting\PaymentPlan;
use App\Services\Quoting\ScheduleNetting;
use PHPUnit\Framework\TestCase;

/**
 * §4.3 netting — walk the new schedule consuming paid-to-date: settled items,
 * then ONE adjustment for the first part-covered item, then due as normal.
 */
class ScheduleNettingTest extends TestCase
{
    private function items(array $amounts): array
    {
        return array_map(fn (float $amount, int $i) => ['key' => 'phase:'.($i + 1), 'label' => 'Phase '.($i + 1), 'amount' => $amount], $amounts, array_keys($amounts));
    }

    /** The spec's worked example, to the ringgit: 2,700 paid → 1,620 adjustment → 4,320 → 2,160. */
    public function test_m_automobile_worked_example(): void
    {
        // R2: lump sum 40/40/20 on RM 10,800 — the schedule comes from the plan itself.
        $plan = PaymentPlan::fromDocument([
            'payment_plan' => 'lump_sum',
            'phases' => [['label' => 'On acceptance', 'pct' => 40], ['label' => 'At demo', 'pct' => 40], ['label' => 'At go-live', 'pct' => 20]],
        ], 10800);
        $this->assertSame([4320.0, 4320.0, 2160.0], array_column($plan->scheduleItems(), 'amount'));

        $net = ScheduleNetting::net(2700, 10800, $plan->scheduleItems());

        $this->assertSame(1620.0, $net['adjustment_myr']);
        $this->assertSame(0.0, $net['overpaid_myr']);
        $this->assertSame(
            [['adjustment', 2700.0, 1620.0], ['due', 0.0, 4320.0], ['due', 0.0, 2160.0]],
            array_map(fn ($i) => [$i['state'], $i['covered_myr'], $i['due_myr']], $net['items']),
        );
        // What's still to collect equals the new total less what was paid.
        $this->assertSame(10800.0 - 2700.0, array_sum(array_column($net['items'], 'due_myr')));
    }

    public function test_items_fully_covered_are_settled_and_the_next_partly_covered_one_adjusts(): void
    {
        $net = ScheduleNetting::net(5000, 10000, $this->items([2000, 2000, 2000, 2000, 2000]));

        $this->assertSame(['settled', 'settled', 'adjustment', 'due', 'due'], array_column($net['items'], 'state'));
        $this->assertSame(1000.0, $net['adjustment_myr']);
    }

    public function test_paid_exactly_to_an_item_boundary_needs_no_adjustment(): void
    {
        $net = ScheduleNetting::net(4320, 10800, $this->items([4320, 4320, 2160]));

        $this->assertSame(['settled', 'due', 'due'], array_column($net['items'], 'state'));
        $this->assertSame(0.0, $net['adjustment_myr']);
    }

    public function test_nothing_paid_leaves_everything_due(): void
    {
        $net = ScheduleNetting::net(0, 10800, $this->items([5400, 5400]));

        $this->assertSame(['due', 'due'], array_column($net['items'], 'state'));
        $this->assertSame(0.0, $net['adjustment_myr']);
    }

    public function test_paid_beyond_the_new_total_flags_overpaid_and_adjusts_nothing(): void
    {
        $net = ScheduleNetting::net(9000, 8000, $this->items([4000, 4000]));

        $this->assertSame(1000.0, $net['overpaid_myr']);
        $this->assertSame(0.0, $net['adjustment_myr']);
        $this->assertSame(['settled', 'settled'], array_column($net['items'], 'state'));
    }
}
