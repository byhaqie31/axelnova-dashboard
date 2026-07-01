<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // The confirmed quotation snapshot (line items, scope, add-ons) rides
        // along only on the detail view — the list stays lean.
        $detailRoute = $request->routeIs('admin.orders.show');

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'quotation_id' => $this->quotation_id,
            'client_id' => $this->client_id,
            'reference_code' => $this->whenLoaded('quotation', fn () => $this->quotation?->reference_code),
            'package_key' => $this->whenLoaded('quotation', fn () => $this->quotation?->package_key),
            'estimate_min_myr' => $this->whenLoaded('quotation', fn () => $this->quotation?->estimate_min_myr),
            'estimate_max_myr' => $this->whenLoaded('quotation', fn () => $this->quotation?->estimate_max_myr),
            'estimate_eta_value' => $this->whenLoaded('quotation', fn () => $this->quotation?->estimate_eta_value),
            'estimate_eta_unit' => $this->whenLoaded('quotation', fn () => $this->quotation?->estimate_eta_unit),
            'submitted_at' => $this->whenLoaded('quotation', fn () => $this->quotation?->submitted_at?->toISOString()),
            'quotation_document' => $this->when($detailRoute && $this->relationLoaded('quotation'), fn () => $this->quotation?->document),
            'quotation_scope' => $this->when($detailRoute && $this->relationLoaded('quotation'), fn () => $this->quotation?->form_payload),
            'quotation_addons' => $this->when(
                $detailRoute && $this->relationLoaded('quotation') && $this->quotation?->relationLoaded('addons'),
                fn () => $this->quotation->addons->map(fn ($a) => [
                    'key' => $a->addon_key,
                    'label' => $a->addon_label,
                    'amount_myr' => $a->amount_myr,
                ]),
            ),
            'name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'email' => $this->whenLoaded('client', fn () => $this->client?->email),
            'phone' => $this->whenLoaded('client', fn () => $this->client?->phone),
            'company' => $this->whenLoaded('client', fn () => $this->client?->company),
            'value_min_myr' => $this->value_min_myr,
            'value_max_myr' => $this->value_max_myr,
            'final_amount_myr' => $this->final_amount_myr,
            'deposit_pct' => $this->deposit_pct,
            'deposit_due_myr' => $this->deposit_due_myr,
            'amount_paid_myr' => $this->amount_paid_myr,
            'remaining_myr' => $this->remaining_myr,
            'payment_status' => $this->payment_status,
            'status' => $this->status,
            'started_at' => $this->started_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'due_at' => $this->due_at?->toDateString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => $this->updatedBy ? [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
            ] : null),
            'invoices' => $this->whenLoaded('invoices', fn () => $this->invoices->map(fn ($d) => [
                'id' => $d->id,
                'type' => $d->type,
                'number' => $d->invoice_number,
                'status' => $d->status,
                'amount_total' => $d->amount_total,
                'amount_paid' => $d->amount_paid,
                'payment_ref' => $d->payment_ref,
                'payment_method' => $d->payment_method,
                'issued_at' => $d->issued_at?->toISOString(),
                'paid_at' => $d->paid_at?->toISOString(),
                'pdf_path' => $d->pdf_path,
            ])),
            'receipts' => $this->whenLoaded('receipts', fn () => $this->receipts->map(fn ($r) => [
                'id' => $r->id,
                'number' => $r->receipt_number,
                'invoice_id' => $r->invoice_id,
                'invoice_number' => $r->invoice?->invoice_number,
                'status' => $r->status,
                'amount' => $r->amount,
                'payment_ref' => $r->payment_ref,
                'payment_method' => $r->payment_method,
                'issued_at' => $r->issued_at?->toISOString(),
                'pdf_path' => $r->pdf_path,
            ])),
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
