<?php

namespace Tests\Feature\Quotations;

use App\Models\PricingConfig;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Quoting\DocumentMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The quotation document's LANGUAGE MODEL: `locale` (en | bm, default en) is a
 * column the admin builder and the connector set explicitly (never detected
 * from content), the PDF data carries it beside ISO dates and data-only
 * payment blocks, and the renderer's locale file supplies every piece of
 * template chrome. Founder-authored content (project, intro, titles) is never
 * touched — it prints exactly as entered whatever the locale.
 */
class QuotationDocumentLocaleTest extends TestCase
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

    /** A detailed admin quotation (RM 14,340) with the given document extras + top-level extras. */
    private function detailedBody(array $documentExtras = [], array $extras = []): array
    {
        return array_merge([
            'name' => 'M Automobile', 'email' => 'm-auto@example.com',
            'document' => array_merge([
                'layout' => 'detailed',
                'deposit_pct' => 19,
                'payload' => [
                    'project' => 'Sistem Bengkel — Pakej B',
                    'intro' => 'Deposit RM 2,700 semasa penerimaan.',
                    'sections' => [[
                        'title' => 'Skop kerja',
                        'rows' => [['title' => 'Sistem + website + care', 'price' => 14340]],
                        'totalLabel' => 'Jumlah skop kerja',
                        'total' => 14340,
                    ]],
                ],
            ], $documentExtras),
        ], $extras);
    }

    // ── Column + admin API ───────────────────────────────────────────────────

    public function test_locale_defaults_to_en(): void
    {
        $res = $this->postJson('/api/v1/admin/quotations', $this->detailedBody(), $this->adminHeaders())
            ->assertCreated()
            ->assertJsonPath('data.locale', 'en');

        $this->assertSame('en', Quotation::findOrFail($res->json('data.id'))->locale);
    }

    public function test_admin_store_and_update_set_the_locale(): void
    {
        $res = $this->postJson('/api/v1/admin/quotations', $this->detailedBody([], ['locale' => 'bm']), $this->adminHeaders())
            ->assertCreated()
            ->assertJsonPath('data.locale', 'bm');
        $id = $res->json('data.id');

        // Absent on update → unchanged.
        $this->putJson("/api/v1/admin/quotations/{$id}", $this->detailedBody(), $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.locale', 'bm');

        $this->putJson("/api/v1/admin/quotations/{$id}", $this->detailedBody([], ['locale' => 'en']), $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('data.locale', 'en');

        $this->postJson('/api/v1/admin/quotations', $this->detailedBody([], ['locale' => 'fr']), $this->adminHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['locale']);
    }

    public function test_preview_carries_the_locale_from_the_draft(): void
    {
        $this->postJson('/api/v1/admin/quotations/preview', $this->detailedBody([], ['locale' => 'bm']), $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('locale', 'bm')
            ->assertJsonPath('project', 'Sistem Bengkel — Pakej B');

        $this->postJson('/api/v1/admin/quotations/preview', $this->detailedBody(), $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('locale', 'en');
    }

    // ── PDF data (DocumentMapper) ────────────────────────────────────────────

    public function test_document_data_carries_locale_iso_dates_and_untouched_content(): void
    {
        $res = $this->postJson('/api/v1/admin/quotations', $this->detailedBody([
            'deposit_amount_myr' => 2700, 'payment_plan' => 'instalment', 'instalment_months' => 12,
            'instalment_amount_myr' => 970, 'billing_day' => 20, 'first_instalment_date' => '2026-11-20',
        ], ['locale' => 'bm']), $this->adminHeaders())->assertCreated();

        $q = Quotation::findOrFail($res->json('data.id'));
        $pdf = DocumentMapper::toDocumentData($q);

        $this->assertSame('bm', $pdf['locale']);
        // Header dates travel as ISO; the template formats them per locale.
        $this->assertSame($q->issuedDate()->toDateString(), $pdf['issued']);
        $this->assertSame($q->validUntil()->toDateString(), $pdf['validUntil']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $pdf['issued']);
        // Founder content is never translated or rewritten.
        $this->assertSame('Sistem Bengkel — Pakej B', $pdf['project']);
        $this->assertSame('Deposit RM 2,700 semasa penerimaan.', $pdf['intro']);
        $this->assertSame('Skop kerja', $pdf['sections'][0]['title']);
        $this->assertSame('Jumlah skop kerja', $pdf['sections'][0]['totalLabel']);
        // The plan block is data only (roles + ISO dates); labels live in the renderer.
        $this->assertSame('2026-11-20', $pdf['paymentPlan']['firstDate']);
        $this->assertSame('inst_monthly', $pdf['panels'][1]['role']);
        $this->assertArrayNotHasKey('label', $pdf['panels'][1]);
    }

    public function test_standard_layout_only_carries_the_plan_block_when_scheduled(): void
    {
        $base = ['name' => 'Acme', 'email' => 'acme@example.com', 'document' => [
            'layout' => 'detailed', 'deposit_pct' => 50,
            'payload' => ['sections' => [['title' => 'Scope', 'rows' => [['title' => 'x', 'price' => 10000]], 'total' => 10000]]],
        ]];
        $q = Quotation::findOrFail($this->postJson('/api/v1/admin/quotations', $base, $this->adminHeaders())->assertCreated()->json('data.id'));

        // Standard-layout rendering of a lump sum: the deposit card, no block.
        $q->document = ['layout' => 'standard', 'deposit_pct' => 50, 'items' => [['title' => 'x', 'qty' => 1, 'rate' => 10000]]];
        $standard = DocumentMapper::toDocumentData($q);
        $this->assertSame('standard', $standard['layout']);
        $this->assertNull($standard['paymentPlan']);
        $this->assertSame('en', $standard['locale']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $standard['issued']);

        // Standard-layout instalment: the block replaces the deposit card.
        $q->document = ['layout' => 'standard', 'deposit_amount_myr' => 2000, 'payment_plan' => 'instalment',
            'instalment_months' => 4, 'instalment_amount_myr' => 2000, 'billing_day' => 20,
            'items' => [['title' => 'x', 'qty' => 1, 'rate' => 10000]]];
        $scheduled = DocumentMapper::toDocumentData($q);
        $this->assertSame('instalment', $scheduled['paymentPlan']['plan']);
        $this->assertCount(4, $scheduled['paymentPlan']['schedule']);
    }
}
