<?php

namespace Tests\Feature\Analytics;

use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\Referrer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cockpit attribution roll-up (payment → order → quotation → inquiry /
 * referral partner). The aggregation moved from PHP loops over whole tables to
 * grouped SQL — these tests pin the old semantics: signed collected sums
 * (refunds net out), inquiry-source buckets with 'direct' fallback, and the
 * per-referrer roll-up with a 'Public' bucket.
 */
class AdminAttributionTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $token = User::factory()->founder()->create()->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_attribution_rolls_up_by_source_and_referrer(): void
    {
        $partner = Referrer::factory()->create(['commission_pct' => 10]);

        // Referred deal: contracted 1000, collected 600 - 100 refund = 500.
        $referredQuote = Quotation::factory()->create(['referral_partner_id' => $partner->id]);
        $referredOrder = Order::factory()->create([
            'quotation_id' => $referredQuote->id,
            'final_amount_myr' => 1000,
        ]);
        Inquiry::create([
            'name' => 'Referred Lead',
            'email' => 'lead@example.com',
            'message' => 'Interested in a build.',
            'source' => 'referral',
            'status' => 'quoted',
            'quotation_id' => $referredQuote->id,
        ]);
        $paid = Payment::factory()->create([
            'order_id' => $referredOrder->id,
            'client_id' => $referredOrder->client_id,
            'amount_myr' => 600,
        ]);
        Payment::factory()->refundOf($paid, 100)->create();

        // Public deal: no inquiry, quotation without a partner. Contracted 2000, collected 300.
        $publicOrder = Order::factory()->create(['final_amount_myr' => 2000]);
        Payment::factory()->create([
            'order_id' => $publicOrder->id,
            'client_id' => $publicOrder->client_id,
            'amount_myr' => 300,
        ]);

        $response = $this->getJson('/api/v1/admin/analytics/attribution', $this->adminHeaders())
            ->assertOk()
            ->json();

        $this->assertSame(3000.0, (float) $response['totals']['contracted']);
        $this->assertSame(800.0, (float) $response['totals']['collected']);

        $bySource = collect($response['bySource'])->keyBy('source');
        $this->assertSame(1, $bySource['referral']['orders']);
        $this->assertSame(500.0, (float) $bySource['referral']['collected']);
        $this->assertSame(1, $bySource['direct']['orders']);
        $this->assertSame(2000.0, (float) $bySource['direct']['contracted']);

        $byReferrer = collect($response['byReferrer'])->keyBy('referrer');
        $this->assertSame(500.0, (float) $byReferrer[$partner->name]['collected']);
        $this->assertSame(10, $byReferrer[$partner->name]['commission_pct']);
        // commission_est = collected × pct.
        $this->assertSame(50.0, (float) $byReferrer[$partner->name]['commission_est']);
        $this->assertSame(300.0, (float) $byReferrer['Public']['collected']);
    }

    public function test_attribution_requires_a_cockpit_token(): void
    {
        $this->getJson('/api/v1/admin/analytics/attribution')->assertUnauthorized();
    }
}
