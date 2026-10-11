<?php

namespace Tests\Feature\Quotations;

use App\Models\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The agreed total of an item-based quotation is computed exactly like the
 * PDF the client signs (computeTotals in frontend/server/utils/pdf/template.ts):
 * subtotal − discount, plus tax at `document.tax_rate` — a FRACTION (0..1,
 * as AdminQuotationRequest validates), not a percentage.
 */
class QuotationFinalAmountTest extends TestCase
{
    use RefreshDatabase;

    private function quotation(array $document): Quotation
    {
        return Quotation::factory()->create(['document' => $document]);
    }

    public function test_no_tax_is_the_plain_subtotal(): void
    {
        $quotation = $this->quotation(['items' => [
            ['title' => 'Site', 'qty' => 1, 'rate' => 5000],
            ['title' => 'CMS', 'qty' => 2, 'rate' => 600],
        ]]);

        $this->assertSame(6200.0, $quotation->finalAmount());
    }

    public function test_tax_rate_is_a_fraction_like_the_pdf(): void
    {
        $quotation = $this->quotation([
            'items' => [['title' => 'Site', 'qty' => 1, 'rate' => 5000]],
            'tax_rate' => 0.06,
        ]);

        // 5000 + 6% — not 5000 × 1.0006 (the old ÷100 reading).
        $this->assertSame(5300.0, $quotation->finalAmount());
    }

    public function test_tax_applies_after_the_discount(): void
    {
        $quotation = $this->quotation([
            'items' => [['title' => 'Site', 'qty' => 1, 'rate' => 5000]],
            'discount' => 1000,
            'tax_rate' => 0.08,
        ]);

        $this->assertSame(4320.0, $quotation->finalAmount());
    }

    public function test_document_totals_break_the_amount_down(): void
    {
        $totals = Quotation::documentTotals([
            'items' => [['title' => 'Site', 'qty' => 1, 'rate' => 5000]],
            'discount' => 1000,
            'tax_rate' => 0.08,
        ]);

        $this->assertSame(['subtotal' => 5000.0, 'discount' => 1000.0, 'tax' => 320.0, 'total' => 4320.0], $totals);
        $this->assertNull(Quotation::documentTotals(['items' => []]));
        $this->assertNull(Quotation::documentTotals(null));
    }
}
