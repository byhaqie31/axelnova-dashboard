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
            ['label' => 'Remaining after this payment', 'price' => 1300.0, 'priceMuted' => true, 'role' => 'remaining'],
        ], $doc['summary']['rows']);

        // Accent "Amount due" panel plus the balance-after panel.
        $this->assertCount(2, $doc['panels']);
        $this->assertSame('Balance after this payment', $doc['panels'][1]['label']);
        $this->assertSame(1300.0, $doc['panels'][1]['value']);
        $this->assertSame('balance', $doc['panels'][1]['role']);
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
            ['label' => 'Remaining after this payment', 'price' => 1000.0, 'priceMuted' => true, 'role' => 'remaining'],
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

    private function deposit(array $input = []): array
    {
        return DocumentMapper::forOrder($this->order(), 'invoice', array_merge([
            'number' => 'AXNI-2026-0020',
            'issued' => '15 July 2026',
            'invoiceType' => 'deposit',
            'amount' => 1300,
        ], $input));
    }

    public function test_defaults_keep_todays_payload_plus_an_all_shown_display_object(): void
    {
        $doc = $this->deposit();

        $this->assertSame(['summary' => true, 'remaining' => true], $doc['display']);
        $this->assertArrayNotHasKey('billingFor', $doc);
        $this->assertArrayNotHasKey('scope', $doc);
        $this->assertSame([
            'layout', 'kind', 'number', 'issued', 'status', 'currency', 'studio',
            'client', 'project', 'subtitle', 'summary', 'panels', 'display',
        ], array_keys($doc));
    }

    public function test_display_switches_hide_at_render_time_and_never_strip_data(): void
    {
        $default = $this->deposit();
        $hidden = $this->deposit(['showSummary' => false, 'showRemaining' => false]);

        $this->assertSame(['summary' => false, 'remaining' => false], $hidden['display']);
        // The remaining row and the balance panel are still stored — the template
        // skips them, the payload keeps them (amount_total is read from the rows).
        $this->assertSame($default['summary'], $hidden['summary']);
        $this->assertSame($default['panels'], $hidden['panels']);
    }

    public function test_billing_for_and_scope_map_from_the_inputs(): void
    {
        $doc = $this->deposit([
            'billingTitle' => '  Deposit on signing and mobilisation ',
            'billingText' => 'Project mobilisation and discovery.',
            'scopeTitle' => '',
            'scopeItems' => ['Kick-off', '  ', 'Discovery', ''],
        ]);

        // Blank label omitted — the template defaults it to "Scope covered".
        $this->assertSame([
            'title' => 'Deposit on signing and mobilisation',
            'text' => 'Project mobilisation and discovery.',
        ], $doc['billingFor']);
        $this->assertSame(['items' => ['Kick-off', 'Discovery']], $doc['scope']);
    }

    public function test_scope_with_only_blank_items_is_skipped(): void
    {
        $doc = $this->deposit(['scopeTitle' => 'Scope covered', 'scopeItems' => ['', '  ']]);

        $this->assertArrayNotHasKey('scope', $doc);
    }

    public function test_receipts_carry_no_display_options(): void
    {
        $doc = DocumentMapper::forOrder($this->order(), 'receipt', [
            'number' => 'AXNR-2026-0001',
            'issued' => '15 July 2026',
            'amount' => 1300,
            'showSummary' => false,
            'billingTitle' => 'Ignored',
        ]);

        $this->assertArrayNotHasKey('display', $doc);
        $this->assertArrayNotHasKey('billingFor', $doc);
    }

    public function test_bill_to_shows_the_company_when_it_differs_from_the_name(): void
    {
        $order = $this->order();
        $order->quotation->company = 'Client Co Sdn Bhd';
        $this->assertSame('Client Co Sdn Bhd', DocumentMapper::forOrder($order, 'invoice', [
            'amount' => 1300,
        ])['client']['company']);

        // Company-only contact: it's already the display name — not printed twice.
        $solo = new Order(['final_amount_myr' => 2600]);
        $solo->setRelation('quotation', new Quotation(['company' => 'Client Co Sdn Bhd']));
        $client = DocumentMapper::forOrder($solo, 'invoice', ['amount' => 1300])['client'];
        $this->assertSame('Client Co Sdn Bhd', $client['name']);
        $this->assertArrayNotHasKey('company', $client);
    }
}
