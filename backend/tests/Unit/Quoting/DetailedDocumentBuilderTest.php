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

        // Deposit / balance panels (50% of 10000).
        $this->assertSame(5000.0, $p['panels'][0]['value']);
        $this->assertSame(5000.0, $p['panels'][1]['value']);
        $this->assertTrue($p['panels'][1]['accent']);

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
}
