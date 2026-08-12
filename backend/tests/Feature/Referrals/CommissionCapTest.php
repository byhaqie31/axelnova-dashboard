<?php

namespace Tests\Feature\Referrals;

use App\Models\Order;
use App\Models\Referral;
use App\Models\Referrer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The per-referral payout ceiling (Referral::COMMISSION_CAP_MYR). The tier
 * bands (5/10/15) stay, but no single referral ever pays out beyond the cap —
 * every derived figure (earned, estimated, legacy amount, portal stats) clamps.
 */
class CommissionCapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cap_leaves_typical_commissions_untouched(): void
    {
        // 15% of a RM 6,000 project — well under the ceiling.
        $this->assertSame(900.0, Referral::capCommission(900.0));
    }

    public function test_the_cap_clamps_oversized_commissions(): void
    {
        // 15% of RM 20,000 would be 3,000 — clamps to the ceiling.
        $this->assertSame(1500.0, Referral::capCommission(3000.0));
    }

    public function test_pending_commission_is_the_plain_remainder_below_the_cap(): void
    {
        // 15% of 6,000 = 900 total; 300 already collected-side → 600 pending.
        $this->assertSame(600.0, Referral::pendingCommission(15, 6000, 2000));
    }

    public function test_pending_commission_stops_accruing_once_the_cap_is_reached(): void
    {
        // Collected side already at the cap (15% × 10,000 = 1,500) → nothing further accrues.
        $this->assertSame(0.0, Referral::pendingCommission(15, 20000, 10000));

        // Collected side at 750 → only the distance to the cap remains, not 15% of the rest.
        $this->assertSame(750.0, Referral::pendingCommission(15, 20000, 5000));
    }

    public function test_legacy_commission_amount_is_capped(): void
    {
        $order = Order::factory()->create(['final_amount_myr' => 20000]);

        $referral = Referral::create([
            'referrer_name' => 'Ref Erra',
            'referrer_email' => 'ref@example.com',
            'business_name' => 'Big Whale Sdn Bhd',
            'relationship_tier' => 'closed',
            'commission_tier_pct' => 15,
            'status' => 'converted',
            'linked_order_id' => $order->id,
        ]);

        $this->assertSame(1500.0, $referral->load('order')->commissionAmount());
    }

    public function test_partner_dashboard_stats_respect_the_cap(): void
    {
        $referrer = Referrer::factory()->credentialed()->create();
        $token = $referrer->account->createToken('partner-portal', ['partner'])->plainTextToken;

        // Fully collected big order: 15% × 20,000 = 3,000 raw → capped at 1,500, nothing pending.
        $order = Order::factory()->create([
            'final_amount_myr' => 20000,
            'amount_paid_myr' => 20000,
        ]);

        Referral::create([
            'referral_partner_id' => $referrer->id,
            'referrer_name' => $referrer->name,
            'referrer_email' => $referrer->email,
            'business_name' => 'Big Whale Sdn Bhd',
            'relationship_tier' => 'closed',
            'commission_tier_pct' => 15,
            'status' => 'converted',
            'quotation_id' => $order->quotation_id,
        ]);

        $response = $this->getJson('/api/v1/partner/dashboard', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('partner.commission_cap_myr', Referral::COMMISSION_CAP_MYR);

        $this->assertEqualsWithDelta(1500.0, $response->json('stats.earned_myr'), 0.001);
        $this->assertEqualsWithDelta(0.0, $response->json('stats.estimated_myr'), 0.001);
        $this->assertEqualsWithDelta(1500.0, $response->json('referrals.0.earned_myr'), 0.001);
    }

    public function test_partner_dashboard_stats_are_unchanged_below_the_cap(): void
    {
        $referrer = Referrer::factory()->credentialed()->create();
        $token = $referrer->account->createToken('partner-portal', ['partner'])->plainTextToken;

        // Typical project: 15% × 6,000 = 900 total, 2,000 collected → 300 earned, 600 pending.
        $order = Order::factory()->create([
            'final_amount_myr' => 6000,
            'amount_paid_myr' => 2000,
        ]);

        Referral::create([
            'referral_partner_id' => $referrer->id,
            'referrer_name' => $referrer->name,
            'referrer_email' => $referrer->email,
            'business_name' => 'Acme Sdn Bhd',
            'relationship_tier' => 'closed',
            'commission_tier_pct' => 15,
            'status' => 'converted',
            'quotation_id' => $order->quotation_id,
        ]);

        $response = $this->getJson('/api/v1/partner/dashboard', ['Authorization' => "Bearer {$token}"])
            ->assertOk();

        $this->assertEqualsWithDelta(300.0, $response->json('stats.earned_myr'), 0.001);
        $this->assertEqualsWithDelta(600.0, $response->json('stats.estimated_myr'), 0.001);
    }
}
