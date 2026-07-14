<?php

namespace Tests\Unit\Quoting;

use App\Models\Order;
use App\Models\Quotation;
use App\Services\Quoting\DocumentMapper;
use PHPUnit\Framework\TestCase;

/**
 * Pins the invoice/receipt document mapping. Free-text notes from the issue
 * form must land as NoteLine[] ({label, text}) — the PDF template maps over
 * them, and a bare string in a frozen payload used to 500 the renderer.
 * Pure — no DB (relations are set in-memory).
 */
class DocumentMapperTest extends TestCase
{
    private function order(): Order
    {
        $order = new Order(['final_amount_myr' => 2600]);
        $order->setRelation('quotation', new Quotation([
            'reference_code' => 'AXNQ-2026-0007',
            'name' => 'One Malaysia Taxi',
            'email' => 'client@example.com',
        ]));

        return $order;
    }

    public function test_amount_invoice_wraps_free_text_notes_into_note_lines(): void
    {
        $doc = DocumentMapper::forOrder($this->order(), 'invoice', [
            'number' => 'AXNI-2026-0002',
            'issued' => '15 July 2026',
            'invoiceType' => 'final',
            'amount' => 1300,
            'notes' => 'Balance Payment',
        ]);

        $this->assertSame([['label' => '', 'text' => 'Balance Payment']], $doc['notes']);
    }

    public function test_itemised_invoice_wraps_notes_and_blank_notes_are_dropped(): void
    {
        $doc = DocumentMapper::forOrder($this->order(), 'invoice', [
            'number' => 'AXNI-2026-0003',
            'issued' => '15 July 2026',
            'notes' => 'Thank you.',
        ]);
        $this->assertSame([['label' => '', 'text' => 'Thank you.']], $doc['notes']);

        $blank = DocumentMapper::forOrder($this->order(), 'invoice', [
            'number' => 'AXNI-2026-0004',
            'issued' => '15 July 2026',
            'notes' => '   ',
        ]);
        $this->assertArrayNotHasKey('notes', $blank);
    }

    public function test_deposit_invoice_shows_the_bill_and_the_remaining_balance(): void
    {
        $doc = DocumentMapper::forOrder($this->order(), 'invoice', [
            'number' => 'AXNI-2026-0010',
            'issued' => '15 July 2026',
            'invoiceType' => 'deposit',
            'amount' => 1300,
        ]);

        $this->assertSame([
            ['label' => 'Agreed project total', 'price' => 2600.0],
            ['label' => 'Deposit due', 'price' => 1300.0, 'total' => true, 'red' => true],
            ['label' => 'Remaining after this payment', 'price' => 1300.0, 'priceMuted' => true],
        ], $doc['summary']['rows']);

        // Accent "Amount due" panel plus the balance-after panel.
        $this->assertCount(2, $doc['panels']);
        $this->assertSame('Balance after this payment', $doc['panels'][1]['label']);
        $this->assertSame(1300.0, $doc['panels'][1]['value']);
    }

    public function test_partial_invoice_shows_paid_to_date_and_remaining(): void
    {
        $order = $this->order();
        $order->amount_paid_myr = 600;

        $doc = DocumentMapper::forOrder($order, 'invoice', [
            'number' => 'AXNI-2026-0011',
            'issued' => '15 July 2026',
            'invoiceType' => 'partial',
            'amount' => 1000,
        ]);

        $this->assertSame([
            ['label' => 'Agreed project total', 'price' => 2600.0],
            ['label' => 'Paid to date', 'price' => 600.0, 'negative' => true, 'green' => true],
            ['label' => 'Partial payment due', 'price' => 1000.0, 'total' => true, 'red' => true],
            ['label' => 'Remaining after this payment', 'price' => 1000.0, 'priceMuted' => true],
        ], $doc['summary']['rows']);
    }

    public function test_final_invoice_shows_paid_to_date_and_no_remaining(): void
    {
        $order = $this->order();
        $order->amount_paid_myr = 1300;

        $doc = DocumentMapper::forOrder($order, 'invoice', [
            'number' => 'AXNI-2026-0012',
            'issued' => '15 July 2026',
            'invoiceType' => 'final',
            'amount' => 1300,
        ]);

        $this->assertSame([
            ['label' => 'Agreed project total', 'price' => 2600.0],
            ['label' => 'Paid to date', 'price' => 1300.0, 'negative' => true, 'green' => true],
            ['label' => 'Final balance due', 'price' => 1300.0, 'total' => true, 'red' => true],
        ], $doc['summary']['rows']);

        // Settles the order — only the accent "Amount due" panel, no balance-after.
        $this->assertCount(1, $doc['panels']);
        $this->assertSame('Amount due', $doc['panels'][0]['label']);
    }

    public function test_already_shaped_note_lines_pass_through(): void
    {
        $lines = [['label' => 'Estimated completion:', 'text' => '4 weeks from deposit.']];

        $doc = DocumentMapper::forOrder($this->order(), 'invoice', [
            'number' => 'AXNI-2026-0005',
            'issued' => '15 July 2026',
            'amount' => 1300,
            'notes' => $lines,
        ]);

        $this->assertSame($lines, $doc['notes']);
    }
}
