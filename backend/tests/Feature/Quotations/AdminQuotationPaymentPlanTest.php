<?php

namespace Tests\Feature\Quotations;

use App\Models\Order;
use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Quoting\DocumentMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin builder's deposit + payment-plan write path: the new keys are
 * validated and stored on the document, the resource serves the derived plan,
 * the standard-layout PDF data uses the fixed amount, and accepting a quote
 * carries a fixed deposit onto the order as an amount (never a recomputed pct).
 */
class AdminQuotationPaymentPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        PricingConfig::factory()->create([
            'config' => ['currency' => 'MYR', 'valid_for_days' => 30, 'rush_multiplier' => 1.20,
                'base_packages' => [], 'modifiers' => [], 'addons' => []],
        ]);
        $this->founder = User::factory()->founder()->create();
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->founder->createToken('admin-spa', ['cockpit'])->plainTextToken];
    }

    /** A detailed admin quotation (one scope section, RM 14,340) with the given document extras. */
    private function detailedBody(array $documentExtras): array
    {
        return [
            'name' => 'M Automobile', 'email' => 'm-auto@example.com',
            'document' => array_merge([
                'layout' => 'detailed',
                'deposit_pct' => 19,
                'payload' => [
                    'project' => 'Sistem Bengkel',
                    'sections' => [[
                        'title' => 'Scope of work',
                        'rows' => [['title' => 'Sistem + website + care', 'price' => 14340]],
                        'totalLabel' => 'Scope of work total',
                        'total' => 14340,
                    ]],
                    // What the builder bakes today from the raw pct — must be overridden on read.
                    'panels' => [
                        ['label' => 'Deposit (19%)', 'value' => 2725, 'note' => 'Payable to commence work.'],
                        ['label' => 'Balance on completion', 'value' => 11615, 'accent' => true, 'note' => 'Due before handover.'],
                    ],
                    'paymentTerms' => ['items' => ['19% deposit to commence; balance due on delivery before handover.']],
                ],
            ], $documentExtras),
        ];
    }

    public function test_store_keeps_the_plan_keys_and_serves_the_derived_plan(): void
    {
        $res = $this->postJson('/api/v1/admin/quotations', $this->detailedBody([
            'deposit_amount_myr' => 2700,
            'payment_plan' => 'instalment',
            'instalment_months' => 12,
            'instalment_amount_myr' => 970,
            'billing_day' => 20,
            'first_instalment_date' => '2026-11-20',
            'includes_care_plan' => true,
        ]), $this->adminHeaders())->assertCreated();

        $res->assertJsonPath('data.payment_plan.plan', 'instalment')
            ->assertJsonPath('data.payment_plan.deposit_amount_myr', 2700)
            ->assertJsonPath('data.payment_plan.deposit_pct_label', '18.8%')
            ->assertJsonPath('data.payment_plan.last_instalment_date', '2027-10-20')
            ->assertJsonPath('data.payment_plan.variance_myr', 0)
            ->assertJsonPath('data.document.deposit_amount_myr', 2700)
            ->assertJsonPath('data.document.payment_plan', 'instalment');

        // The PDF data overrides the stale baked panels + terms with the agreed figures.
        $q = Quotation::findOrFail($res->json('data.id'));
        $pdf = DocumentMapper::toDocumentData($q);
        $this->assertSame(2700.0, $pdf['panels'][0]['value']);
        $this->assertSame(970.0, $pdf['panels'][1]['value']);
        $this->assertSame(14340.0, $pdf['paymentPlan']['total']);
        $this->assertStringStartsWith('RM 2,700 deposit to commence;', $pdf['paymentTerms']['items'][0]);
        $json = json_encode($pdf);
        $this->assertStringNotContainsString('19%', $json);
        $this->assertStringNotContainsString('2725', $json);
        $this->assertStringNotContainsString('11615', $json);
    }

    public function test_legacy_pct_only_document_is_left_exactly_as_stored(): void
    {
        $res = $this->postJson('/api/v1/admin/quotations', $this->detailedBody([]), $this->adminHeaders())->assertCreated();

        $q = Quotation::findOrFail($res->json('data.id'));
        $pdf = DocumentMapper::toDocumentData($q);
        // No new keys → the writer's panels are passed through untouched (only the
        // terms bullet is realigned, as before).
        $this->assertSame('Deposit (19%)', $pdf['panels'][0]['label']);
        $this->assertEquals(2725, $pdf['panels'][0]['value']);
        $this->assertArrayNotHasKey('paymentPlan', $pdf);
        $res->assertJsonPath('data.payment_plan.plan', 'lump_sum')
            ->assertJsonPath('data.payment_plan.deposit_fixed', false)
            ->assertJsonPath('data.payment_plan.deposit_amount_myr', 2725);
    }

    public function test_scheduled_plan_without_months_or_amount_is_rejected(): void
    {
        $this->postJson('/api/v1/admin/quotations', $this->detailedBody([
            'payment_plan' => 'instalment',
        ]), $this->adminHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['document.instalment_months', 'document.instalment_amount_myr']);

        $this->postJson('/api/v1/admin/quotations', $this->detailedBody([
            'payment_plan' => 'instalment', 'instalment_months' => 12, 'instalment_amount_myr' => 970, 'billing_day' => 29,
        ]), $this->adminHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['document.billing_day']);
    }

    public function test_standard_layout_pdf_data_uses_the_fixed_deposit(): void
    {
        $q = Quotation::factory()->create([
            'document' => [
                'layout' => 'standard',
                'items' => [['title' => 'Website', 'qty' => 1, 'rate' => 10000]],
                'deposit_pct' => 50,
                'deposit_amount_myr' => 2700,
            ],
        ]);

        $pdf = DocumentMapper::toDocumentData($q);
        $this->assertSame(2700.0, $pdf['depositAmount']);
        $this->assertSame('27%', $pdf['depositPctLabel']);
        $this->assertSame(50, $pdf['depositPct']); // legacy key still served
        $this->assertStringStartsWith('RM 2,700 deposit (27%) to commence;', $pdf['terms'][0]);
        $this->assertNull($pdf['paymentPlan']);
    }

    public function test_accepting_a_fixed_deposit_quote_carries_the_amount_onto_the_order(): void
    {
        $res = $this->postJson('/api/v1/admin/quotations', $this->detailedBody([
            'deposit_amount_myr' => 2700,
            'payment_plan' => 'instalment',
            'instalment_months' => 12,
            'instalment_amount_myr' => 970,
        ]), $this->adminHeaders())->assertCreated();

        $this->postJson("/api/v1/admin/quotations/{$res->json('data.id')}/accept", [], $this->adminHeaders())->assertOk();

        $order = Order::where('quotation_id', $res->json('data.id'))->firstOrFail();
        $this->assertEquals(14340, $order->final_amount_myr);
        $this->assertNull($order->deposit_pct);
        $this->assertEquals(2700, $order->deposit_amount_myr);
        $this->assertSame(2700.0, $order->deposit_due_myr);
    }

    public function test_accepting_a_pct_deposit_quote_still_carries_the_pct(): void
    {
        $res = $this->postJson('/api/v1/admin/quotations', $this->detailedBody(['deposit_pct' => 40]), $this->adminHeaders())->assertCreated();

        $this->postJson("/api/v1/admin/quotations/{$res->json('data.id')}/accept", [], $this->adminHeaders())->assertOk();

        $order = Order::where('quotation_id', $res->json('data.id'))->firstOrFail();
        $this->assertSame(40, $order->deposit_pct);
        $this->assertNull($order->deposit_amount_myr);
        $this->assertSame(5736.0, $order->deposit_due_myr); // 40% of 14,340
    }
}
