<?php

namespace Tests\Unit\Quoting;

use App\Services\Quoting\PaymentPlan;
use PHPUnit\Framework\TestCase;

/**
 * The dynamic by-phase lump sum: any ordered split that adds up to 100% —
 * 40/40/20, 50/50, 20/20/20/20/20 — priced to the ringgit, the last phase
 * absorbing the rounding so the phases always sum to the total.
 */
class PaymentPlanPhasesTest extends TestCase
{
    private function phased(array $pcts, float $total): PaymentPlan
    {
        return PaymentPlan::fromDocument([
            'payment_plan' => 'lump_sum',
            'phases' => array_map(fn ($pct, $i) => ['label' => 'P'.($i + 1), 'pct' => $pct], $pcts, array_keys($pcts)),
        ], $total);
    }

    public function test_splits_of_any_shape_sum_to_the_total(): void
    {
        $this->assertSame([4320.0, 4320.0, 2160.0], $this->phased([40, 40, 20], 10800)->phaseAmounts());
        $this->assertSame([5400.0, 5400.0], $this->phased([50, 50], 10800)->phaseAmounts());
        $this->assertSame([2160.0, 2160.0, 2160.0, 2160.0, 2160.0], $this->phased([20, 20, 20, 20, 20], 10800)->phaseAmounts());

        // 33.33 / 33.33 / 33.34 of RM 1,000: 333 + 333, the last takes the remainder.
        $this->assertSame([333.0, 333.0, 334.0], $this->phased([33.33, 33.33, 33.34], 1000)->phaseAmounts());
    }

    public function test_phase_one_is_the_up_front_payment(): void
    {
        $plan = $this->phased([40, 40, 20], 10800);

        $this->assertTrue($plan->isPhased());
        $this->assertSame(4320.0, $plan->depositAmount());
        $this->assertSame('40%', $plan->depositPctLabel());
        $this->assertSame('Phase 2 of 3 · P2', $plan->phaseLabel(2));
        $this->assertSame([], $plan->panels(), 'phases print as a table, not deposit/balance cards');
        $this->assertSame(
            'RM 4,320.00 (40%) to commence; then RM 4,320.00 (40%) P2; RM 2,160.00 (20%) P3.',
            $plan->depositTerm(),
        );
        // The stored boilerplate realigns when the split changes.
        $this->assertSame(
            [$this->phased([50, 50], 10800)->depositTerm()],
            $this->phased([50, 50], 10800)->alignTerms([$plan->depositTerm()]),
        );
    }

    public function test_an_invalid_split_falls_back_to_the_classic_lump_sum(): void
    {
        $this->assertFalse($this->phased([40, 40], 10800)->isPhased(), 'must add up to 100%');
        $this->assertFalse($this->phased([100], 10800)->isPhased(), 'needs at least two phases');
        $this->assertFalse($this->phased([60, 40, 0], 10800)->isPhased(), 'every phase must be > 0');
        $this->assertFalse(PaymentPlan::fromDocument([
            'payment_plan' => 'instalment', 'instalment_months' => 12, 'instalment_amount_myr' => 900,
            'phases' => [['pct' => 50], ['pct' => 50]],
        ], 10800)->isPhased(), 'phases only split a lump sum');
    }

    public function test_schedule_items_per_plan(): void
    {
        $this->assertSame(['deposit', 'balance'], array_column(PaymentPlan::fromDocument(['deposit_pct' => 50], 10000)->scheduleItems(), 'key'));
        $this->assertSame(['phase:1', 'phase:2'], array_column($this->phased([50, 50], 10000)->scheduleItems(), 'key'));

        $instalment = PaymentPlan::fromDocument([
            'deposit_amount_myr' => 2700, 'payment_plan' => 'instalment', 'instalment_months' => 12,
            'instalment_amount_myr' => 970, 'first_instalment_date' => '2026-11-20',
        ], 14340)->scheduleItems();
        $this->assertCount(13, $instalment);
        $this->assertSame(['deposit', 2700.0], [$instalment[0]['key'], $instalment[0]['amount']]);
        $this->assertSame(['instalment:12', 970.0, '2027-10-20'], [$instalment[12]['key'], $instalment[12]['amount'], $instalment[12]['date']]);
    }

    public function test_care_kind(): void
    {
        $this->assertSame('included', PaymentPlan::fromDocument([
            'payment_plan' => 'instalment', 'instalment_months' => 12, 'instalment_amount_myr' => 970, 'includes_care_plan' => true,
        ], 14340)->careKind());
        $this->assertSame('separate', PaymentPlan::fromDocument(['care_plan_monthly_myr' => 220], 10800)->careKind());
        $this->assertNull(PaymentPlan::fromDocument([], 10800)->careKind());
    }
}
