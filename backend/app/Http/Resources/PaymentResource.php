<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A ledger row for the admin Payments module. Client/order/invoice context ride
 * along from eager-loaded relations; `refundable_myr` is the amount still
 * refundable (needs `refunds` loaded), `receipt` is present once one is issued.
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'type' => $this->type->value,
            'gateway' => $this->gateway->value,
            'method' => $this->method->value,
            'status' => $this->status->value,
            'amount_myr' => $this->amount_myr,
            'fee_myr' => $this->fee_myr,
            'net_myr' => $this->net_myr,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'parent_payment_id' => $this->parent_payment_id,
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice?->invoice_number),
            'client_id' => $this->client_id,
            'name' => $this->whenLoaded('client', fn () => $this->client?->name),
            'email' => $this->whenLoaded('client', fn () => $this->client?->email),
            'recorded_by_name' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy?->name),
            // Amount still refundable on a payment row (refunds carry negative amounts).
            'refundable_myr' => $this->when(
                $this->relationLoaded('refunds') && $this->type === PaymentType::Payment,
                fn () => number_format(
                    max(0, (float) $this->amount_myr + (float) $this->refunds->where('status', PaymentStatus::Succeeded)->sum('amount_myr')),
                    2, '.', ''
                )
            ),
            'receipt' => $this->whenLoaded('receipt', fn () => $this->receipt ? [
                'id' => $this->receipt->id,
                'number' => $this->receipt->receipt_number,
                'pdf_path' => $this->receipt->pdf_path,
            ] : null),
        ];
    }
}
