<?php

namespace Tests\Unit\Quoting;

use App\Services\Quoting\PaymentPlan;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the deposit + payment-plan arithmetic that every quotation renderer
 * (PDF mapper, admin resource, connector view) derives from the stored
 * document: fixed amount wins over percentage, pct → amount rounds to the
 * nearest ringgit, the effective pct is derived (never stored), instalment
 * totals reconcile against the quotation total, and schedule dates roll
 * correctly across month ends and year ends. Pure — no DB.
 */
class PaymentPlanTest extends TestCase
{
    // ── Deposit: pct → amount ────────────────────────────────────────────────

    #[DataProvider('pctRoundingCases')]
    public function test_pct_deposit_rounds_to_the_nearest_ringgit(float $total, int $pct, float $expected): void
    {
        $plan = PaymentPlan::fromDocument(['deposit_pct' => $pct], $total);

        $this->assertSame($expected, $plan->depositAmount());
        $this->assertFalse($plan->isFixedDeposit());
    }

    public static function pctRoundingCases(): array
    {
        return [
            'exact half' => [10000, 50, 5000.0],
            '19% of 14340 = 2724.60 → 2725' => [14340, 19, 2725.0],
            'exact ringgit' => [2470, 50, 1235.0],
            '.50 rounds away from zero' => [2469, 50, 1235.0], // 1234.50 → 1235
            '33% of 1000 = 330' => [1000, 33, 330.0],
            '33% of 1234 = 407.22 → 407' => [1234, 33, 407.0],
            'decimal total' => [1999.99, 50, 1000.0],          // 999.995 → 1000
        ];
    }

    public function test_missing_or_zero_pct_falls_back_to_fifty_percent(): void
    {
        $this->assertSame(50, PaymentPlan::fromDocument([], 1000)->depositPct());
        $this->assertSame(50, PaymentPlan::fromDocument(['deposit_pct' => 0], 1000)->depositPct());
        $this->assertSame(500.0, PaymentPlan::fromDocument(null, 1000)->depositAmount());
    }

    // ── Deposit: fixed amount wins ───────────────────────────────────────────

    public function test_fixed_amount_wins_over_pct_and_derives_the_effective_pct(): void
    {
        $plan = PaymentPlan::fromDocument(['deposit_pct' => 19, 'deposit_amount_myr' => 2700], 14340);

        $this->assertTrue($plan->isFixedDeposit());
        $this->assertSame(2700.0, $plan->depositAmount());
        $this->assertSame(11640.0, $plan->balance());
        // 2700 / 14340 = 18.828…% — displayed to one decimal, never stored.
        $this->assertEqualsWithDelta(18.828, $plan->effectiveDepositPct(), 0.001);
        $this->assertSame('18.8%', $plan->depositPctLabel());
        $this->assertSame('RM 2,700 · 18.8%', $plan->depositLabel());
        // The stored pct is still readable (legacy consumers), but is not what renders.
        $this->assertSame(19, $plan->depositPct());
    }

    public function test_integral_effective_pct_label_has_no_decimal(): void
    {
        $this->assertSame('50%', PaymentPlan::fromDocument(['deposit_pct' => 50], 10000)->depositPctLabel());
        $this->assertSame('25%', PaymentPlan::fromDocument(['deposit_amount_myr' => 2500], 10000)->depositPctLabel());
        $this->assertSame('RM 5,000 · 50%', PaymentPlan::fromDocument(['deposit_pct' => 50], 10000)->depositLabel());
    }

    public function test_fixed_amount_is_clamped_to_the_total(): void
    {
        $plan = PaymentPlan::fromDocument(['deposit_amount_myr' => 20000], 14340);

        $this->assertSame(14340.0, $plan->depositAmount());
        $this->assertSame(0.0, $plan->balance());
        $this->assertSame('100%', $plan->depositPctLabel());
    }

    public function test_zero_total_never_divides_by_zero(): void
    {
        $plan = PaymentPlan::fromDocument(['deposit_amount_myr' => 100], 0);

        $this->assertSame(0.0, $plan->effectiveDepositPct());
        $this->assertSame('0%', $plan->depositPctLabel());
    }

    // ── Plans ────────────────────────────────────────────────────────────────

    public function test_defaults_to_lump_sum_with_no_schedule_and_zero_variance(): void
    {
        $plan = PaymentPlan::fromDocument(['deposit_pct' => 50], 10000);

        $this->assertSame(PaymentPlan::LUMP_SUM, $plan->plan());
        $this->assertFalse($plan->isScheduled());
        $this->assertSame([], $plan->schedule());
        $this->assertSame(10000.0, $plan->planTotal());
        $this->assertSame(0.0, $plan->variance());
        $this->assertNull($plan->documentBlock());
    }

    public function test_unknown_plan_values_fall_back_to_lump_sum(): void
    {
        $this->assertSame(PaymentPlan::LUMP_SUM, PaymentPlan::fromDocument(['payment_plan' => 'weird'], 100)->plan());
    }

    public function test_instalment_total_reconciles_against_the_quotation_total(): void
    {
        $plan = PaymentPlan::fromDocument([
            'payment_plan' => 'instalment',
            'deposit_amount_myr' => 2700,
            'instalment_months' => 12,
            'instalment_amount_myr' => 970,
        ], 14340);

        $this->assertTrue($plan->isScheduled());
        $this->assertSame(14340.0, $plan->planTotal()); // 2700 + 12 × 970
        $this->assertSame(0.0, $plan->variance());
        $this->assertTrue($plan->reconciles());
    }

    public function test_a_deliberately_rounded_monthly_figure_reports_the_variance(): void
    {
        $plan = PaymentPlan::fromDocument([
            'payment_plan' => 'instalment',
            'deposit_amount_myr' => 2700,
            'instalment_months' => 12,
            'instalment_amount_myr' => 975,
        ], 14340);

        $this->assertSame(14400.0, $plan->planTotal());
        $this->assertSame(-60.0, $plan->variance()); // plan collects RM 60 more than quoted
        $this->assertFalse($plan->reconciles());
    }

    public function test_partner_plan_defaults_to_24_months(): void
    {
        $plan = PaymentPlan::fromDocument([
            'payment_plan' => 'partner',
            'deposit_amount_myr' => 1000,
            'instalment_amount_myr' => 500,
        ], 13000);

        $this->assertSame(PaymentPlan::PARTNER, $plan->plan());
        $this->assertSame(24, $plan->months());
        $this->assertSame(13000.0, $plan->planTotal());
        $this->assertSame(0.0, $plan->variance());
    }

    public function test_billing_day_defaults_to_20_and_clamps_to_1_28(): void
    {
        $this->assertSame(20, PaymentPlan::fromDocument(['payment_plan' => 'instalment'], 100)->billingDay());
        $this->assertSame(28, PaymentPlan::fromDocument(['payment_plan' => 'instalment', 'billing_day' => 31], 100)->billingDay());
        $this->assertSame(1, PaymentPlan::fromDocument(['payment_plan' => 'instalment', 'billing_day' => 0], 100)->billingDay());
    }

    // ── Schedule ─────────────────────────────────────────────────────────────

    public function test_schedule_runs_from_the_first_date_on_the_billing_day_across_year_end(): void
    {
        $plan = PaymentPlan::fromDocument([
            'payment_plan' => 'instalment',
            'deposit_amount_myr' => 2700,
            'instalment_months' => 12,
            'instalment_amount_myr' => 970,
            'billing_day' => 20,
            'first_instalment_date' => '2026-11-20',
        ], 14340);

        $schedule = $plan->schedule();

        $this->assertCount(12, $schedule);
        $this->assertSame(['n' => 1, 'date' => '2026-11-20', 'amount' => 970.0], $schedule[0]);
        $this->assertSame('2026-12-20', $schedule[1]['date']);
        $this->assertSame('2027-01-20', $schedule[2]['date']); // rolls into the new year
        $this->assertSame('2027-10-20', $schedule[11]['date']);
        $this->assertSame('2026-11-20', $plan->firstInstalmentDate()?->toDateString());
        $this->assertSame('2027-10-20', $plan->lastInstalmentDate()?->toDateString());
        $this->assertSame(970.0 * 12, array_sum(array_column($schedule, 'amount')));
    }

    public function test_first_instalment_keeps_its_own_day_then_later_ones_use_the_billing_day(): void
    {
        $plan = PaymentPlan::fromDocument([
            'payment_plan' => 'instalment',
            'instalment_months' => 3,
            'instalment_amount_myr' => 100,
            'billing_day' => 20,
            'first_instalment_date' => '2026-11-05',
        ], 300);

        $dates = array_column($plan->schedule(), 'date');

        $this->assertSame(['2026-11-05', '2026-12-20', '2027-01-20'], $dates);
    }

    public function test_missing_first_date_starts_on_the_next_billing_day_after_the_anchor(): void
    {
        $anchor = CarbonImmutable::parse('2026-10-10');

        // Billing day still ahead this month → this month.
        $ahead = PaymentPlan::fromDocument([
            'payment_plan' => 'instalment', 'instalment_months' => 2, 'instalment_amount_myr' => 50, 'billing_day' => 20,
        ], 100, $anchor);
        $this->assertSame(['2026-10-20', '2026-11-20'], array_column($ahead->schedule(), 'date'));

        // Billing day already passed (or is today) → next month.
        $passed = PaymentPlan::fromDocument([
            'payment_plan' => 'instalment', 'instalment_months' => 2, 'instalment_amount_myr' => 50, 'billing_day' => 10,
        ], 100, $anchor);
        $this->assertSame(['2026-11-10', '2026-12-10'], array_column($passed->schedule(), 'date'));
    }

    #[DataProvider('monthEndCases')]
    public function test_schedule_dates_clamp_to_the_last_day_of_short_months(string $first, int $billingDay, array $expected): void
    {
        $dates = [];
        $start = CarbonImmutable::parse($first);
        foreach (range(0, count($expected) - 1) as $i) {
            $dates[] = PaymentPlan::scheduleDate($start, $i, $billingDay)->toDateString();
        }

        $this->assertSame($expected, $dates);
    }

    public static function monthEndCases(): array
    {
        return [
            // Non-leap February clamps 31 → 28; the run recovers to 31 in March.
            'day 31 across Feb 2027' => ['2027-01-31', 31, ['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30', '2027-05-31']],
            // Leap February keeps the 29th.
            'day 31 across Feb 2028' => ['2028-01-31', 31, ['2028-01-31', '2028-02-29', '2028-03-31']],
            'day 30 across Feb' => ['2027-01-30', 30, ['2027-01-30', '2027-02-28', '2027-03-30', '2027-04-30']],
            'day 29 non-leap Feb' => ['2027-01-29', 29, ['2027-01-29', '2027-02-28', '2027-03-29']],
            'day 29 leap Feb' => ['2028-01-29', 29, ['2028-01-29', '2028-02-29', '2028-03-29']],
            // Year end.
            'day 28 over new year' => ['2026-12-28', 28, ['2026-12-28', '2027-01-28', '2027-02-28']],
        ];
    }

    // ── Document block (PDF) ─────────────────────────────────────────────────

    public function test_instalment_document_block_carries_the_exact_agreed_figures(): void
    {
        $plan = PaymentPlan::fromDocument([
            'payment_plan' => 'instalment',
            'deposit_pct' => 19,
            'deposit_amount_myr' => 2700,
            'instalment_months' => 12,
            'instalment_amount_myr' => 970,
            'billing_day' => 20,
            'first_instalment_date' => '2026-11-20',
            'includes_care_plan' => true,
        ], 14340);

        $block = $plan->documentBlock();

        $this->assertSame('instalment', $block['plan']);
        $this->assertSame(2700.0, $block['deposit']);
        $this->assertSame(970.0, $block['monthly']);
        $this->assertSame(12, $block['months']);
        $this->assertSame(20, $block['billingDay']);
        $this->assertSame('20 November 2026', $block['firstDate']);
        $this->assertSame('20 Oktober 2027', $block['lastDate']);
        $this->assertSame(14340.0, $block['total']);
        $this->assertTrue($block['includesCarePlan']);
        $this->assertCount(12, $block['schedule']);

        // The forbidden stale figures never appear anywhere in the block.
        $json = json_encode($block);
        $this->assertStringNotContainsString('19%', $json);
        $this->assertStringNotContainsString('2,725', $json);
        $this->assertStringNotContainsString('2725', $json);
    }

    public function test_partner_document_block_shows_setup_plus_monthly_times_months(): void
    {
        $block = PaymentPlan::fromDocument([
            'payment_plan' => 'partner',
            'deposit_amount_myr' => 1000,
            'instalment_amount_myr' => 500,
            'first_instalment_date' => '2027-01-20',
        ], 13000)->documentBlock();

        $this->assertSame('partner', $block['plan']);
        $this->assertSame(1000.0, $block['deposit']);
        $this->assertSame(500.0, $block['monthly']);
        $this->assertSame(24, $block['months']);
        $this->assertSame(13000.0, $block['total']);
        $this->assertSame('20 Disember 2028', $block['lastDate']);
    }

    // ── Panels + terms ───────────────────────────────────────────────────────

    public function test_lump_sum_panels_show_deposit_pct_and_balance_on_completion(): void
    {
        $panels = PaymentPlan::fromDocument(['deposit_pct' => 50], 10000)->panels();

        $this->assertSame('Deposit (50%)', $panels[0]['label']);
        $this->assertSame(5000.0, $panels[0]['value']);
        $this->assertSame('Balance on completion', $panels[1]['label']);
        $this->assertSame(5000.0, $panels[1]['value']);
        $this->assertTrue($panels[1]['accent']);
    }

    public function test_fixed_deposit_panels_show_the_effective_pct(): void
    {
        $panels = PaymentPlan::fromDocument(['deposit_pct' => 19, 'deposit_amount_myr' => 2700], 14340)->panels();

        $this->assertSame('Deposit (18.8%)', $panels[0]['label']);
        $this->assertSame(2700.0, $panels[0]['value']);
        $this->assertSame(11640.0, $panels[1]['value']);
    }

    public function test_instalment_panels_show_deposit_and_monthly(): void
    {
        $panels = PaymentPlan::fromDocument([
            'payment_plan' => 'instalment', 'deposit_amount_myr' => 2700,
            'instalment_months' => 12, 'instalment_amount_myr' => 970,
        ], 14340)->panels();

        $this->assertSame(2700.0, $panels[0]['value']);
        $this->assertSame(970.0, $panels[1]['value']);
        $this->assertStringContainsString('12', $panels[1]['label']);
    }

    public function test_deposit_term_matches_the_plan(): void
    {
        $this->assertSame(
            '50% deposit to commence; balance due on delivery before handover.',
            PaymentPlan::fromDocument(['deposit_pct' => 50], 10000)->depositTerm(),
        );
        $this->assertSame(
            'RM 2,700 deposit (18.8%) to commence; balance due on delivery before handover.',
            PaymentPlan::fromDocument(['deposit_amount_myr' => 2700], 14340)->depositTerm(),
        );
        $this->assertSame(
            'RM 2,700 deposit to commence; balance of RM 11,640 payable in 12 monthly instalments of RM 970, billed on the 20th of each month.',
            PaymentPlan::fromDocument([
                'payment_plan' => 'instalment', 'deposit_amount_myr' => 2700,
                'instalment_months' => 12, 'instalment_amount_myr' => 970, 'billing_day' => 20,
            ], 14340)->depositTerm(),
        );
        $this->assertSame(
            'RM 1,000 setup fee to commence; then RM 500 monthly for 24 months, billed on the 20th of each month.',
            PaymentPlan::fromDocument([
                'payment_plan' => 'partner', 'deposit_amount_myr' => 1000, 'instalment_amount_myr' => 500,
            ], 13000)->depositTerm(),
        );
    }

    public function test_to_array_is_the_admin_and_connector_projection(): void
    {
        $arr = PaymentPlan::fromDocument([
            'payment_plan' => 'instalment', 'deposit_amount_myr' => 2700,
            'instalment_months' => 12, 'instalment_amount_myr' => 970,
            'billing_day' => 20, 'first_instalment_date' => '2026-11-20', 'includes_care_plan' => true,
        ], 14340)->toArray();

        $this->assertSame('instalment', $arr['plan']);
        $this->assertTrue($arr['deposit_fixed']);
        $this->assertSame(2700.0, $arr['deposit_amount_myr']);
        $this->assertSame('18.8%', $arr['deposit_pct_label']);
        $this->assertSame(12, $arr['instalment_months']);
        $this->assertSame(970.0, $arr['instalment_amount_myr']);
        $this->assertSame(20, $arr['billing_day']);
        $this->assertSame('2026-11-20', $arr['first_instalment_date']);
        $this->assertSame('2027-10-20', $arr['last_instalment_date']);
        $this->assertTrue($arr['includes_care_plan']);
        $this->assertSame(14340.0, $arr['plan_total_myr']);
        $this->assertSame(0.0, $arr['variance_myr']);
        $this->assertCount(12, $arr['schedule']);
    }
}
