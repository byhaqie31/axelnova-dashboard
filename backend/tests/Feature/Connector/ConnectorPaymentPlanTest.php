<?php

namespace Tests\Feature\Connector;

use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Quoting\DocumentMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The connector's deposit + payment-plan contract on a detailed draft, pinned
 * by the case that exposed the gap — AXNQ-2026-0017 (M Automobile Service):
 * total RM 14,340, a FIXED RM 2,700 deposit, then 12 × RM 970 from 20 Nov 2026
 * on the 20th, care plan included. The stored document, the connector read-back
 * and the PDF data must carry exactly those figures — and never the rounded
 * "19%" / "RM 2,725" that a whole-number deposit_pct would have produced.
 */
class ConnectorPaymentPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PricingConfig::factory()->create([
            'config' => ['currency' => 'MYR', 'valid_for_days' => 30, 'rush_multiplier' => 1.20,
                'base_packages' => [], 'modifiers' => [], 'addons' => []],
        ]);
    }

    private function connectorHeader(): array
    {
        $token = User::factory()->founder()->create()
            ->createToken('mcp-connector', ['connector:read', 'connector:draft'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    /** The AXNQ-2026-0017 brief as the connector would send it (sections sum to RM 14,340). */
    private function mAutomobileDetailed(array $overrides = []): array
    {
        return array_merge([
            'subtitle' => 'Sebut harga sistem bengkel',
            // What an older client would still send — must never leak into the output.
            'deposit_pct' => 19,
            'deposit_amount_myr' => 2700,
            'payment_plan' => 'instalment',
            'instalment_months' => 12,
            'instalment_amount_myr' => 970,
            'billing_day' => 20,
            'first_instalment_date' => '2026-11-20',
            'includes_care_plan' => true,
            'sections' => [
                ['title' => 'Sistem bengkel', 'rows' => [
                    ['title' => 'Invois penuh', 'detail' => 'Invois, resit, laporan', 'amount_myr' => 9000],
                ]],
                ['title' => 'Website', 'rows' => [
                    ['title' => 'Laman web syarikat', 'amount_myr' => 2700],
                ]],
                ['title' => 'Care Plan 12 bulan', 'rows' => [
                    ['title' => 'Hosting, domain & sokongan', 'amount_myr' => 2640],
                ]],
            ],
            'care' => [['label' => 'Care Plan', 'detail' => 'Selepas bulan ke-12', 'amount_myr' => 220, 'period' => 'month']],
        ], $overrides);
    }

    private function draft(array $detailed): TestResponse
    {
        return $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Pengurusan M Automobile Service', 'email' => 'm-auto@example.com', 'company' => 'M Automobile Service Sdn. Bhd.'],
            'project' => 'Sistem Bengkel — Pakej B · Ansuran 12 bulan',
            'intro' => 'Deposit RM 2,700 semasa penerimaan, kemudian RM 970 sebulan × 12.',
            'detailed' => $detailed,
        ], $this->connectorHeader());
    }

    public function test_axnq_2026_0017_fixture_carries_the_agreed_figures_everywhere(): void
    {
        $res = $this->draft($this->mAutomobileDetailed())->assertCreated();

        // Connector read-back: the derived plan, not the stale pct.
        $res->assertJsonPath('data.estimate.max_myr', 14340)
            ->assertJsonPath('data.payment_plan.plan', 'instalment')
            ->assertJsonPath('data.payment_plan.deposit_fixed', true)
            ->assertJsonPath('data.payment_plan.deposit_amount_myr', 2700)
            ->assertJsonPath('data.payment_plan.deposit_pct_label', '18.8%')
            ->assertJsonPath('data.payment_plan.instalment_months', 12)
            ->assertJsonPath('data.payment_plan.instalment_amount_myr', 970)
            ->assertJsonPath('data.payment_plan.billing_day', 20)
            ->assertJsonPath('data.payment_plan.first_instalment_date', '2026-11-20')
            ->assertJsonPath('data.payment_plan.last_instalment_date', '2027-10-20')
            ->assertJsonPath('data.payment_plan.includes_care_plan', true)
            ->assertJsonPath('data.payment_plan.plan_total_myr', 14340)
            ->assertJsonPath('data.payment_plan.variance_myr', 0)
            ->assertJsonPath('data.payment_plan.reconciles', true);
        $this->assertStringNotContainsString('NOTE:', $res->json('message'));

        // Stored document: the inputs beside deposit_pct, nothing derived written back.
        $q = Quotation::where('reference_code', $res->json('data.reference_code'))->firstOrFail();
        $doc = $q->document;
        $this->assertSame(19, $doc['deposit_pct']);
        $this->assertEquals(2700, $doc['deposit_amount_myr']); // JSON round-trip may drop the .0
        $this->assertSame('instalment', $doc['payment_plan']);
        $this->assertSame(12, $doc['instalment_months']);
        $this->assertEquals(970, $doc['instalment_amount_myr']);
        $this->assertSame(20, $doc['billing_day']);
        $this->assertSame('2026-11-20', $doc['first_instalment_date']);
        $this->assertTrue($doc['includes_care_plan']);
        $this->assertSame(2700.0, $q->depositAmountMyr());

        // The PDF data (what DocumentController serves the renderer).
        $pdf = DocumentMapper::toDocumentData($q->fresh());
        $this->assertSame('detailed', $pdf['layout']);
        $this->assertSame('en', $pdf['locale']);
        $this->assertSame(2700.0, $pdf['panels'][0]['value']);
        $this->assertSame('inst_deposit', $pdf['panels'][0]['role']);
        $this->assertSame(970.0, $pdf['panels'][1]['value']);
        $this->assertSame('inst_monthly', $pdf['panels'][1]['role']);
        $this->assertSame(12, $pdf['panels'][1]['months']);

        $block = $pdf['paymentPlan'];
        $this->assertSame('instalment', $block['plan']);
        $this->assertSame(2700.0, $block['deposit']);
        $this->assertSame(970.0, $block['monthly']);
        $this->assertSame(12, $block['months']);
        $this->assertSame(20, $block['billingDay']);
        $this->assertSame('2026-11-20', $block['firstDate']);
        $this->assertSame('2027-10-20', $block['lastDate']);
        $this->assertSame(14340.0, $block['total']);
        $this->assertTrue($block['includesCarePlan']);
        $this->assertCount(12, $block['schedule']);
        $this->assertSame('2026-11-20', $block['schedule'][0]['date']);
        $this->assertSame('2027-10-20', $block['schedule'][11]['date']);
        // The founder's BM content prints as authored; no BM chrome is baked by the backend.
        $this->assertSame('Sistem Bengkel — Pakej B · Ansuran 12 bulan', $pdf['project']);
        $this->assertStringNotContainsString('Pelan pembayaran', json_encode($pdf));

        // The terms bullet names the agreed figures, not a pct.
        $this->assertStringContainsString('RM 2,700 deposit to commence', $pdf['paymentTerms']['items'][0]);
        $this->assertStringContainsString('12 monthly instalments of RM 970', $pdf['paymentTerms']['items'][0]);

        // And nowhere in the whole PDF payload do the stale figures survive.
        $json = json_encode($pdf, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('19%', $json);
        $this->assertStringNotContainsString('2,725', $json);
        $this->assertStringNotContainsString('2725', $json);
        $this->assertStringNotContainsString('2,724', $json);
        $this->assertStringNotContainsString('2724', $json);
    }

    public function test_deposit_pct_only_keeps_working_for_old_connector_clients(): void
    {
        $res = $this->draft($this->mAutomobileDetailed([
            'deposit_pct' => 30,
            'deposit_amount_myr' => null,
            'payment_plan' => null,
            'instalment_months' => null,
            'instalment_amount_myr' => null,
            'billing_day' => null,
            'first_instalment_date' => null,
            'includes_care_plan' => null,
        ]))->assertCreated();

        $res->assertJsonPath('data.payment_plan.plan', 'lump_sum')
            ->assertJsonPath('data.payment_plan.deposit_fixed', false)
            ->assertJsonPath('data.payment_plan.deposit_amount_myr', 4302)   // 30% of 14,340
            ->assertJsonPath('data.payment_plan.deposit_pct_label', '30%');

        $q = Quotation::where('reference_code', $res->json('data.reference_code'))->firstOrFail();
        // Legacy shape: deposit_pct only — none of the new keys are written.
        $this->assertSame(30, $q->document['deposit_pct']);
        foreach (['deposit_amount_myr', 'payment_plan', 'instalment_months', 'instalment_amount_myr', 'billing_day', 'first_instalment_date', 'includes_care_plan'] as $key) {
            $this->assertArrayNotHasKey($key, $q->document, "{$key} must not be written for a pct-only draft");
        }

        $pdf = DocumentMapper::toDocumentData($q->fresh());
        // Cards are derived on read (role + figures); the renderer labels them per locale.
        $this->assertSame(['role' => 'lump_deposit', 'value' => 4302.0, 'pctLabel' => '30%'], $pdf['panels'][0]);
        $this->assertSame('lump_balance', $pdf['panels'][1]['role']);
        $this->assertSame('lump_sum', $pdf['paymentPlan']['plan']);
        $this->assertSame([], $pdf['paymentPlan']['schedule']);
        $this->assertStringStartsWith('30% deposit to commence;', $pdf['paymentTerms']['items'][0]);
    }

    public function test_fixed_deposit_on_a_lump_sum_shows_the_effective_pct(): void
    {
        $res = $this->draft($this->mAutomobileDetailed([
            'payment_plan' => 'lump_sum',
            'instalment_months' => null,
            'instalment_amount_myr' => null,
        ]))->assertCreated();

        $res->assertJsonPath('data.payment_plan.plan', 'lump_sum')
            ->assertJsonPath('data.payment_plan.deposit_amount_myr', 2700)
            ->assertJsonPath('data.payment_plan.balance_myr', 11640);

        $q = Quotation::where('reference_code', $res->json('data.reference_code'))->firstOrFail();
        $pdf = DocumentMapper::toDocumentData($q);
        $this->assertSame('18.8%', $pdf['panels'][0]['pctLabel']);
        $this->assertSame(2700.0, $pdf['panels'][0]['value']);
        $this->assertSame(11640.0, $pdf['panels'][1]['value']);
        $this->assertStringStartsWith('RM 2,700 deposit (18.8%) to commence;', $pdf['paymentTerms']['items'][0]);
        $this->assertStringNotContainsString('19%', json_encode($pdf));
    }

    public function test_a_rounded_monthly_figure_is_accepted_and_the_variance_reported(): void
    {
        $res = $this->draft($this->mAutomobileDetailed(['instalment_amount_myr' => 975]))->assertCreated();

        $res->assertJsonPath('data.payment_plan.plan_total_myr', 14400)
            ->assertJsonPath('data.payment_plan.variance_myr', -60)
            ->assertJsonPath('data.payment_plan.reconciles', false);
        $this->assertStringContainsString('NOTE: the payment plan collects RM 60.00 more than the quotation total', $res->json('message'));
    }

    public function test_instalment_plan_requires_months_and_a_monthly_amount(): void
    {
        $this->draft($this->mAutomobileDetailed(['instalment_months' => null, 'instalment_amount_myr' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['detailed.instalment_months', 'detailed.instalment_amount_myr']);

        $this->draft($this->mAutomobileDetailed(['billing_day' => 31]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['detailed.billing_day']);
    }

    public function test_partner_plan_defaults_to_24_months(): void
    {
        $res = $this->draft($this->mAutomobileDetailed([
            'payment_plan' => 'partner',
            'deposit_amount_myr' => 2340,
            'instalment_months' => null,
            'instalment_amount_myr' => 500,
            'first_instalment_date' => '2026-11-20',
        ]))->assertCreated();

        $res->assertJsonPath('data.payment_plan.plan', 'partner')
            ->assertJsonPath('data.payment_plan.instalment_months', 24)
            ->assertJsonPath('data.payment_plan.plan_total_myr', 14340) // 2,340 + 24 × 500
            ->assertJsonPath('data.payment_plan.last_instalment_date', '2028-10-20');

        $q = Quotation::where('reference_code', $res->json('data.reference_code'))->firstOrFail();
        $pdf = DocumentMapper::toDocumentData($q);
        $this->assertSame('partner_setup', $pdf['panels'][0]['role']);
        $this->assertSame('partner_monthly', $pdf['panels'][1]['role']);
        $this->assertSame(24, $pdf['paymentPlan']['months']);
        $this->assertSame('partner', $pdf['paymentPlan']['plan']);
        $this->assertStringStartsWith('RM 2,340 setup fee to commence; then RM 500 monthly for 24 months', $pdf['paymentTerms']['items'][0]);
    }

    public function test_update_re_derives_the_plan_from_the_new_inputs(): void
    {
        $ref = $this->draft($this->mAutomobileDetailed())->assertCreated()->json('data.reference_code');

        $res = $this->putJson("/api/v1/connector/quotations/{$ref}", [
            'reseed_document' => true,
            'client' => ['name' => 'Pengurusan M Automobile Service', 'email' => 'm-auto@example.com'],
            'detailed' => $this->mAutomobileDetailed(['instalment_months' => 6, 'instalment_amount_myr' => 1940]),
        ], $this->connectorHeader())->assertOk();

        $res->assertJsonPath('data.payment_plan.instalment_months', 6)
            ->assertJsonPath('data.payment_plan.plan_total_myr', 14340)
            ->assertJsonPath('data.payment_plan.last_instalment_date', '2027-04-20');
    }

    // ── Document locale ──────────────────────────────────────────────────────

    public function test_locale_defaults_to_en_and_is_stored_when_sent(): void
    {
        $en = $this->draft($this->mAutomobileDetailed())->assertCreated();
        $en->assertJsonPath('data.locale', 'en');

        $res = $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Pengurusan M Automobile Service', 'email' => 'm-auto@example.com'],
            'project' => 'Sistem Bengkel — Pakej B · Ansuran 12 bulan',
            'locale' => 'bm',
            'detailed' => $this->mAutomobileDetailed(),
        ], $this->connectorHeader())->assertCreated();

        $ref = $res->json('data.reference_code');
        $res->assertJsonPath('data.locale', 'bm');
        $this->getJson("/api/v1/connector/quotations/{$ref}", $this->connectorHeader())
            ->assertOk()
            ->assertJsonPath('data.locale', 'bm');

        $q = Quotation::where('reference_code', $ref)->firstOrFail();
        $this->assertSame('bm', $q->locale);
        $this->assertSame('bm', DocumentMapper::toDocumentData($q)['locale']);
    }

    public function test_update_keeps_the_locale_unless_a_new_one_is_sent(): void
    {
        $ref = $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Pengurusan M Automobile Service', 'email' => 'm-auto@example.com'],
            'locale' => 'bm',
            'detailed' => $this->mAutomobileDetailed(),
        ], $this->connectorHeader())->assertCreated()->json('data.reference_code');

        $this->putJson("/api/v1/connector/quotations/{$ref}", [
            'reseed_document' => true,
            'client' => ['name' => 'Pengurusan M Automobile Service', 'email' => 'm-auto@example.com'],
            'detailed' => $this->mAutomobileDetailed(['instalment_months' => 6, 'instalment_amount_myr' => 1940]),
        ], $this->connectorHeader())->assertOk()->assertJsonPath('data.locale', 'bm');

        $this->putJson("/api/v1/connector/quotations/{$ref}", [
            'reseed_document' => true,
            'client' => ['name' => 'Pengurusan M Automobile Service', 'email' => 'm-auto@example.com'],
            'locale' => 'en',
            'detailed' => $this->mAutomobileDetailed(),
        ], $this->connectorHeader())->assertOk()->assertJsonPath('data.locale', 'en');
    }

    public function test_unknown_locale_is_rejected(): void
    {
        $this->postJson('/api/v1/connector/quotations/draft', [
            'client' => ['name' => 'Pengurusan M Automobile Service', 'email' => 'm-auto@example.com'],
            'locale' => 'fr',
            'detailed' => $this->mAutomobileDetailed(),
        ], $this->connectorHeader())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['locale']);
    }
}
