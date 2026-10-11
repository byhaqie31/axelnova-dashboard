<?php

namespace App\Http\Controllers\Api\V1\Connector;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * get_order (connector v5.1, ability: connector:read) — READ-ONLY money view of
 * an order, so Claude can check that an order tallies after a quotation
 * revision is accepted (docs/global/QUOTATION-REVISIONS.md): what was paid,
 * what is invoiced and still open, what is still to bill, how each payment of
 * the plan was netted, and every invoice with its status and void reason.
 *
 * Money stays human-only: there is no write here, and no public PDF token is
 * exposed (same rule as the quotation view). Invoices are issued, edited and
 * sent in the admin.
 */
class OrderController extends Controller
{
    /** By AXNO order number, or by the AXNQ reference of the quotation it came from. */
    public function show(string $reference): JsonResponse
    {
        $order = str_starts_with(strtoupper($reference), 'AXNO-')
            ? Order::where('order_number', $reference)->first()
            : Quotation::where('reference_code', $reference)->first()?->order()->first();

        if (! $order) {
            $quotation = Quotation::where('reference_code', $reference)->first();

            return response()->json([
                'message' => $quotation
                    ? "Quotation {$reference} has no order yet — it becomes one when the founder accepts it in the admin (it is '{$quotation->status}')."
                    : "No order or quotation found for '{$reference}'. Pass an AXNO order number or the AXNQ reference of an accepted quotation (see list_quotations).",
            ], 404);
        }

        $order->load(['client', 'quotation', 'currentRevision', 'invoices.revision', 'payments.invoice', 'carePlans.revision']);

        return response()->json(['data' => self::view($order)]);
    }

    /** @return array<string, mixed> */
    private static function view(Order $order): array
    {
        $plan = $order->paymentPlan();
        $total = round((float) $order->final_amount_myr, 2);
        $paid = round((float) $order->amount_paid_myr, 2);

        // What is invoiced and still owed on live project invoices (Care Plan
        // months bill a separate subscription and sit outside the agreed total).
        $open = $order->invoices
            ->where('status', 'issued')
            ->where('type', '!=', 'care_plan')
            ->sum(fn (Invoice $i) => max(0, (float) $i->amount_total - (float) $i->amount_paid));
        $open = round($open, 2);
        $toBill = round(max(0, $total - $paid - $open), 2);
        $overBilled = round(max(0, $paid + $open - $total), 2);

        $quotation = $order->quotation;
        $revisionNumber = $order->currentRevision?->number ?? 1;

        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'quotation' => [
                'reference_code' => $quotation?->reference_code,
                'display_reference' => $quotation ? QuotationRevision::displayReference($quotation->reference_code, $revisionNumber) : null,
                'revision_number' => $revisionNumber,
                'revised_at' => $order->revised_at?->toISOString(),
            ],
            'client' => [
                'name' => $order->client?->name,
                'email' => $order->client?->email,
                'company' => $order->client?->company,
            ],
            // agreed_total = paid_to_date + open_invoiced + still_to_bill when it
            // tallies; over_billed > 0 means live invoices ask for more than is left.
            'tally' => [
                'agreed_total_myr' => $total,
                'paid_to_date_myr' => $paid,
                'open_invoiced_myr' => $open,
                'still_to_bill_myr' => $toBill,
                'over_billed_myr' => $overBilled,
                'overpaid_myr' => (float) ($order->revision_flags['overpaid_myr'] ?? max(0, round($paid - $total, 2))),
                'tallies' => $overBilled < 0.005 && $paid <= $total + 0.005,
                'payment_status' => $order->payment_status,
            ],
            'flags' => $order->revision_flags,
            // The agreed plan, each instalment / phase with its invoice and — after
            // a revision — its netting state (settled / adjustment / due).
            'payment_plan' => $order->planView() ?? [
                'plan' => $plan->plan(),
                'phased' => false,
                'deposit_label' => $plan->upfrontLabel(),
                'deposit_myr' => $order->deposit_due_myr,
                'balance_on_completion_myr' => round($total - (float) $order->deposit_due_myr, 2),
            ],
            'invoices' => $order->invoices->sortBy('issued_at')->values()->map(fn (Invoice $i) => [
                'number' => $i->invoice_number,
                'label' => self::invoiceLabel($i, $order),
                'type' => $i->type,
                'status' => $i->status,
                'void_reason' => $i->void_reason,
                'revision_number' => $i->revision?->number,
                'amount_myr' => (float) $i->amount_total,
                'paid_myr' => (float) ($i->amount_paid ?? 0),
                'issued_at' => $i->issued_at?->toDateString(),
                'due_at' => $i->due_at?->toDateString(),
                'is_overdue' => $i->status === 'issued' && $i->due_at !== null && $i->due_at->lt(today()),
            ])->all(),
            'payments' => $order->payments->sortBy('paid_at')->values()->map(fn (Payment $p) => [
                'number' => $p->payment_number,
                'type' => $p->type->value,
                'status' => $p->status->value,
                'method' => $p->method->value,
                'amount_myr' => (float) $p->amount_myr,
                'paid_at' => $p->paid_at?->toDateString(),
                'invoice_number' => $p->invoice?->invoice_number,
            ])->all(),
            'care_plans' => $order->carePlans->map(fn ($c) => [
                'kind' => $c->kind,
                'monthly_myr' => $c->monthly_myr !== null ? (float) $c->monthly_myr : null,
                'starts_on' => $c->starts_on?->toDateString(),
                'ends_on' => $c->ends_on?->toDateString(),
                'ended' => $c->ended_at !== null,
                'end_reason' => $c->end_reason,
                'revision_number' => $c->revision?->number,
            ])->values()->all(),
            'revisions' => $quotation
                ? $quotation->revisions()->get()
                    ->reject(fn (QuotationRevision $r) => $r->status === QuotationRevision::DISCARDED)
                    ->map(fn (QuotationRevision $r) => [
                        'number' => $r->number,
                        'status' => $r->status,
                        'estimate' => $r->asQuotation($quotation)->finalAmount(),
                        'change_reason' => $r->change_reason,
                        'accepted_at' => $r->accepted_at?->toISOString(),
                        'superseded_at' => $r->superseded_at?->toISOString(),
                    ])->values()->all()
                : [],
            'admin_url' => rtrim((string) config('services.frontend.url'), '/')."/admin/orders/{$order->id}",
        ];
    }

    /** "Phase 2 of 3 · At demo", "Deposit top-up, R1 → R2", "Care Plan · November 2026" … */
    private static function invoiceLabel(Invoice $invoice, Order $order): string
    {
        $inputs = is_array($invoice->inputs) ? $invoice->inputs : [];
        $current = $invoice->revision_id === null || $invoice->revision_id === $order->current_revision_id;
        $plan = $current ? $order->paymentPlan() : null;

        return match ($invoice->type) {
            'adjustment' => (string) ($inputs['adjustmentLabel'] ?? 'Adjustment'),
            'care_plan' => 'Care Plan'.($invoice->care_period ? ' · '.CarbonImmutable::instance($invoice->care_period)->format('F Y') : ''),
            'phase' => $plan?->isPhased() && $invoice->instalment_no ? $plan->phaseLabel($invoice->instalment_no) : "Phase {$invoice->instalment_no}",
            'instalment' => $plan?->isScheduled() && $invoice->instalment_no ? $plan->instalmentLabel($invoice->instalment_no) : "Instalment {$invoice->instalment_no}",
            'deposit' => $plan ? $plan->upfrontLabel() : 'Deposit',
            'partial' => 'Partial payment',
            'final' => 'Final balance',
            default => ucfirst((string) $invoice->type),
        };
    }
}
