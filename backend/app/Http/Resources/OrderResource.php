<?php

namespace App\Http\Resources;

use App\Models\QuotationRevision;
use App\Services\Quoting\ScopeSummary;
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
            // The revision the order runs on — "AXNQ-2026-0017 · R2" after a revise.
            'revision_number' => $this->whenLoaded('currentRevision', fn () => $this->currentRevision?->number),
            'display_reference' => $this->when(
                $this->relationLoaded('quotation') && $this->relationLoaded('currentRevision'),
                fn () => $this->quotation ? QuotationRevision::displayReference($this->quotation->reference_code, $this->currentRevision?->number ?? 1) : null,
            ),
            'revised_at' => $this->revised_at?->toISOString(),
            // Overpaid / care-plan-changed flags raised by the last supersede.
            'revision_flags' => $this->revision_flags,
            // Detail only: the revision chain for the order timeline (dates + reasons).
            'revisions' => $this->when($detailRoute && $this->relationLoaded('quotation'), fn () => $this->quotation
                ? $this->quotation->revisions()->with('creator:id,name')->get()
                    ->reject(fn (QuotationRevision $r) => $r->status === QuotationRevision::DISCARDED)
                    ->map(fn (QuotationRevision $r) => [
                        'number' => $r->number,
                        'label' => $r->label(),
                        'status' => $r->status,
                        'is_current' => $r->id === $this->current_revision_id,
                        'change_reason' => $r->change_reason,
                        'estimate_myr' => $r->asQuotation($this->quotation)->finalAmount(),
                        'created_by' => $r->creator?->name,
                        'created_at' => $r->created_at?->toISOString(),
                        'sent_at' => $r->sent_at?->toISOString(),
                        'accepted_at' => $r->accepted_at?->toISOString(),
                        'superseded_at' => $r->superseded_at?->toISOString(),
                    ])->values()
                : []),
            // Detail only: Care Plan coverage periods (QuotationRevisions §3.5).
            'care_plans' => $this->when($detailRoute, fn () => $this->carePlans->map(fn ($p) => [
                'kind' => $p->kind,
                'monthly_myr' => $p->monthly_myr !== null ? (float) $p->monthly_myr : null,
                'starts_on' => $p->starts_on?->toDateString(),
                'ends_on' => $p->ends_on?->toDateString(),
                'ended_at' => $p->ended_at?->toISOString(),
                'end_reason' => $p->end_reason,
                'revision_number' => $p->revision?->number,
            ])),
            'package_key' => $this->whenLoaded('quotation', fn () => $this->quotation?->package_key),
            'estimate_min_myr' => $this->whenLoaded('quotation', fn () => $this->quotation?->estimate_min_myr),
            'estimate_max_myr' => $this->whenLoaded('quotation', fn () => $this->quotation?->estimate_max_myr),
            'estimate_eta_value' => $this->whenLoaded('quotation', fn () => $this->quotation?->estimate_eta_value),
            'estimate_eta_unit' => $this->whenLoaded('quotation', fn () => $this->quotation?->estimate_eta_unit),
            'submitted_at' => $this->whenLoaded('quotation', fn () => $this->quotation?->submitted_at?->toISOString()),
            'quotation_document' => $this->when($detailRoute && $this->relationLoaded('quotation'), fn () => $this->quotation?->document),
            // Curated per-package scope groups (normalizer-read, scalars only) —
            // never the raw form_payload, whose audit objects aren't displayable.
            'quotation_scope' => $this->when($detailRoute && $this->relationLoaded('quotation'), fn () => $this->quotation ? ScopeSummary::forQuotation($this->quotation) : []),
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
            'deposit_amount_myr' => $this->deposit_amount_myr,
            'deposit_due_myr' => $this->deposit_due_myr,
            // The agreed instalment / partner schedule with each instalment's
            // live invoice (detail view only — it reads invoices + the quotation).
            'payment_plan' => $this->when($detailRoute, fn () => $this->planView()),
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
                'instalment_no' => $d->instalment_no,
                'care_period' => $d->care_period?->format('Y-m'),
                'number' => $d->invoice_number,
                'status' => $d->status,
                'void_reason' => $d->void_reason,
                'revision_id' => $d->revision_id,
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
