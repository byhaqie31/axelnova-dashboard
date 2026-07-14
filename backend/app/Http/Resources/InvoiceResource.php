<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Services\Quoting\DocumentIssuer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cross-order invoice list/detail shape. Client + order context ride along from
 * the eager-loaded `order` relation; `is_overdue` is derived (issued + past due).
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'quotation_id' => $this->whenLoaded('order', fn () => $this->order?->quotation_id),
            'reference_code' => $this->whenLoaded('order', fn () => $this->order?->quotation?->reference_code),
            'client_id' => $this->whenLoaded('order', fn () => $this->order?->client_id),
            'name' => $this->whenLoaded('order', fn () => $this->order?->client?->name),
            'email' => $this->whenLoaded('order', fn () => $this->order?->client?->email),
            'type' => $this->type,
            'status' => $this->status,
            'amount_total' => $this->amount_total,
            'amount_paid' => $this->amount_paid,
            'due_at' => $this->due_at?->toDateString(),
            'issued_at' => $this->issued_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            // Issued and past its due date — an unpaid bill that's late.
            'is_overdue' => $this->status === 'issued' && $this->due_at && $this->due_at->lt(today()),
            'pdf_path' => $this->pdf_path,
            'emailed_at' => $this->emailed_at?->toISOString(),
            'emailed_to' => $this->emailed_to,
            // Detail view only — issue-form fields for the edit page (stored, or
            // the legacy fallback for pre-`inputs` invoices), and whether
            // amount-bearing fields are locked (money recorded against the invoice).
            'inputs' => $this->whenLoaded('payments', fn () => DocumentIssuer::effectiveInputs($this->resource)),
            'amounts_locked' => $this->whenLoaded('payments', fn () => $this->status === 'paid'
                || $this->payments->contains(fn ($p) => $p->status === PaymentStatus::Succeeded)),
            // Detail view only — the ledger rows allocated to this invoice.
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'payment_number' => $p->payment_number,
                'type' => $p->type->value,
                'method' => $p->method->value,
                'status' => $p->status->value,
                'amount_myr' => $p->amount_myr,
                'reference' => $p->reference,
                'paid_at' => $p->paid_at?->toISOString(),
            ])),
        ];
    }
}
