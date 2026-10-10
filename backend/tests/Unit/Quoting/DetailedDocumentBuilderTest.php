<?php

namespace Tests\Unit\Quoting;

use App\Services\Quoting\DetailedDocumentBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Pins the connector's detailed-document build: priced sections → section totals
 * (what sumDetailedSections reads), the summary + deposit panels, and the optional
 * "What's included" / option-card / care-plan blocks. Pure — no DB.
 */
class DetailedDocumentBuilderTest extends TestCase
{
    public function test_builds_priced_sections_summary_and_deposit_panels(): void
    {
        $out = (new DetailedDocumentBuilder)->build([
            'subtitle' => 'Website quotation',
            'deposit_pct' => 50,
            'sections' => [
                ['title' => 'Design', 'rows' => [
                    ['title' => 'Brand + UI', 'detail' => 'Figma', 'amount_myr' => 3000],
                    ['title' => 'Prototype', 'amount_myr' => 1000],
                ]],
                ['title' => 'Build', 'rows' => [
                    ['title' => 'Front-end', 'amount_myr' => 6000],
                ]],
            ],
        ], 'Acme website', 'A clean marketing site.');

        $doc = $out['document'];
        $this->assertSame('detailed', $doc['layout']);
        $this->assertSame(50, $doc['deposit_pct']);
        $this->assertSame(10000.0, $out['total']); // (3000+1000) + 6000

        $p = $doc['payload'];
        $this->assertSame('Acme website', $p['project']);
        $this->assertSame('A clean marketing site.', $p['intro']);
        $this->assertSame('Website quotation', $p['subtitle']);

        // Sections carry a total each — this is what Quotation::sumDetailedSections reads.
        $this->assertCount(2, $p['sections']);
        $this->assertSame(4000.0, $p['sections'][0]['total']);
        $this->assertSame(6000.0, $p['sections'][1]['total']);
        $this->assertSame('Brand + UI', $p['sections'][0]['rows'][0]['title']);
        $this->assertSame(3000.0, $p['sections'][0]['rows'][0]['price']);

        // Summary = per-section rows + a "Project total" grand row.
        $summaryRows = $p['summary']['rows'];
        $last = end($summaryRows);
        $this->assertSame('Project total', $last['label']);
        $this->assertSame(10000.0, $last['price']);

        // Deposit / balance cards are derived by the PDF mapper on read, never baked.
        $this->assertArrayNotHasKey('panels', $p);

        // Standard payment terms carried through.
        $this->assertCount(3, $p['paymentTerms']['items']);
    }

    public function test_builds_included_options_and_care_blocks_and_omits_empty(): void
    {
        $out = (new DetailedDocumentBuilder)->build([
            'sections' => [['title' => 'Scope', 'rows' => [['title' => 'x', 'amount_myr' => 1000]]]],
            'included' => [
                ['eyebrow' => 'SEO', 'items' => ['Sitemap', 'Meta tags'], 'columns' => 2, 'note' => 'basic'],
            ],
            'options' => [
                ['badge' => 'OPTION A', 'title' => 'Standard', 'amount_myr' => 4000, 'recommended' => true, 'was_myr' => 5000, 'price_note' => 'one-time'],
            ],
            'care' => [
                ['label' => 'Basic', 'detail' => 'Hosting + updates', 'amount_myr' => 200, 'period' => 'month'],
            ],
        ], null, null);

        $p = $out['document']['payload'];

        $this->assertSame(['Sitemap', 'Meta tags'], $p['included'][0]['items']);
        $this->assertSame(2, $p['included'][0]['columns']);
        $this->assertSame('SEO', $p['included'][0]['eyebrow']);

        $this->assertSame('Package options', $p['options']['title']);
        $this->assertSame('Standard', $p['options']['cards'][0]['title']);
        $this->assertTrue($p['options']['cards'][0]['accent']);
        $this->assertSame(4000.0, $p['options']['cards'][0]['price']);
        $this->assertSame(5000.0, $p['options']['cards'][0]['priceWas']);

        $this->assertSame('Care & support', $p['care']['title']);
        $this->assertSame('Basic', $p['care']['rows'][0]['label']);
        $this->assertSame('month', $p['care']['rows'][0]['period']);

        // Empty project/intro/subtitle are dropped (never rendered as blanks).
        $this->assertArrayNotHasKey('project', $p);
        $this->assertArrayNotHasKey('intro', $p);
        $this->assertArrayNotHasKey('subtitle', $p);
    }

    public function test_omits_option_and_care_blocks_when_absent(): void
    {
        $out = (new DetailedDocumentBuilder)->build([
            'sections' => [['title' => 'Scope', 'rows' => [['title' => 'x', 'amount_myr' => 1000]]]],
        ], 'P', null);

        $p = $out['document']['payload'];
        $this->assertArrayNotHasKey('options', $p);
        $this->assertArrayNotHasKey('care', $p);
        $this->assertArrayNotHasKey('included', $p);
    }

    public function test_fixed_deposit_amount_wins_over_pct_in_panels_terms_and_the_stored_document(): void
    {
        $out = (new DetailedDocumentBuilder)->build([
            'deposit_pct' => 19,
            'deposit_amount_myr' => 2700,
            'sections' => [['title' => 'Scope', 'rows' => [['title' => 'x', 'amount_myr' => 14340]]]],
        ], 'P', null);

        $doc = $out['document'];
        $this->assertSame(19, $doc['deposit_pct']);
        $this->assertSame(2700.0, $doc['deposit_amount_myr']);
        $this->assertArrayNotHasKey('payment_plan', $doc); // only the keys sent are stored

        $p = $doc['payload'];
        // Deposit / balance cards are derived by the PDF mapper at render time
        // (and labelled from the renderer's locale file) — never baked here.
        $this->assertArrayNotHasKey('panels', $p);
        $this->assertStringStartsWith('RM 2,700 deposit (18.8%) to commence;', $p['paymentTerms']['items'][0]);
        $this->assertStringNotContainsString('19%', json_encode($p));
    }

    public function test_instalment_plan_stores_its_inputs_and_bakes_deposit_plus_monthly_panels(): void
    {
        $out = (new DetailedDocumentBuilder)->build([
            'deposit_amount_myr' => 2700,
            'payment_plan' => 'instalment',
            'instalment_months' => 12,
            'instalment_amount_myr' => 970,
            'billing_day' => 20,
            'first_instalment_date' => '2026-11-20',
            'includes_care_plan' => true,
            'sections' => [['title' => 'Scope', 'rows' => [['title' => 'x', 'amount_myr' => 14340]]]],
        ], 'P', null);

        $doc = $out['document'];
        $this->assertSame('instalment', $doc['payment_plan']);
        $this->assertSame(12, $doc['instalment_months']);
        $this->assertSame(970.0, $doc['instalment_amount_myr']);
        $this->assertSame(20, $doc['billing_day']);
        $this->assertSame('2026-11-20', $doc['first_instalment_date']);
        $this->assertTrue($doc['includes_care_plan']);

        $p = $doc['payload'];
        $this->assertArrayNotHasKey('panels', $p);
        $this->assertStringContainsString('12 monthly instalments of RM 970', $p['paymentTerms']['items'][0]);
    }
}
