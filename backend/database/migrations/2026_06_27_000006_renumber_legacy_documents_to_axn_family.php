<?php

use App\Models\Invoice;
use App\Models\Receipt;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Bring already-issued invoices/receipts onto the AXN family (AXNI / AXNR) so
     * the whole system is consistent. Only legacy non-AXN numbers are touched; the
     * frozen payload's `number` is patched in place — the snapshot is otherwise
     * untouched (not re-mapped from live data).
     *
     * NOTE: this intentionally renumbers issued documents. The pre-AXN rows were
     * dev/seed data. If a deployment carries real client-sent INV-/RCP- documents,
     * scope or skip this migration — a renumber would no longer match the copy the
     * client holds.
     */
    public function up(): void
    {
        Invoice::withTrashed()
            ->where('invoice_number', 'not like', 'AXNI-%')
            ->orderBy('id')
            ->each(function (Invoice $invoice) {
                $number = ReferenceCodeGenerator::generate(DocumentType::Invoice);
                $payload = $invoice->payload;
                if (is_array($payload)) {
                    $payload['number'] = $number;
                }
                $invoice->forceFill(['invoice_number' => $number, 'payload' => $payload])->saveQuietly();
            });

        Receipt::withTrashed()
            ->where('receipt_number', 'not like', 'AXNR-%')
            ->orderBy('id')
            ->each(function (Receipt $receipt) {
                $number = ReferenceCodeGenerator::generate(DocumentType::Receipt);
                $payload = $receipt->payload;
                if (is_array($payload)) {
                    $payload['number'] = $number;
                }
                $receipt->forceFill(['receipt_number' => $number, 'payload' => $payload])->saveQuietly();
            });
    }

    public function down(): void
    {
        // Renumbering isn't cleanly reversible (originals aren't retained). No-op.
    }
};
