<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Jobs\SendInvoiceEmail;
use App\Models\Invoice;
use App\Services\Quoting\DocumentIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Cross-order invoices — the standalone Invoices module. Invoices are issued
 * from the order detail (DocumentIssuer) and their paid state is
 * observer-maintained. Editing re-runs DocumentMapper over the stored issue
 * inputs (same AXNI number); amount-bearing fields lock once money is recorded.
 */
class InvoicesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Invoice::with(['order.client', 'order.quotation'])->latest('issued_at');

        // `overdue` is a derived state (issued + past due), not a stored status.
        $status = $request->query('status');
        if ($status === 'overdue') {
            $query->where('status', 'issued')->whereDate('due_at', '<', today());
        } elseif ($status) {
            $query->where('status', $status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('payment_ref', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('order.client', function ($c) use ($search) {
                        $c->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return InvoiceResource::collection($query->paginate(20));
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        $invoice->load(['order.client', 'order.quotation', 'payments']);

        return new InvoiceResource($invoice);
    }

    /**
     * Re-edit an issued invoice in place (same AXNI number). Paid and void
     * invoices are fully read-only; a partially-paid issued invoice locks its
     * amount-bearing fields — only notes / due date may change.
     */
    public function update(Request $request, Invoice $invoice): InvoiceResource
    {
        abort_if($invoice->status === 'void', 409, 'Void invoices are read-only.');
        abort_if($invoice->status === 'paid', 409, 'Paid invoices are read-only.');

        $data = $request->validate([
            'invoiceType' => ['nullable', 'in:deposit,partial,final'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'amountPaid' => ['nullable', 'numeric', 'min:0'],
            'paymentRef' => ['nullable', 'string', 'max:120'],
            'paymentMethod' => ['nullable', 'string', 'max:120'],
            'discountType' => ['nullable', 'in:amount,percent'],
            'discountValue' => ['nullable', 'numeric', 'min:0'],
            'discountLabel' => ['nullable', 'string', 'max:60'],
            'promoCode' => ['nullable', 'string', 'max:40'],
            'promoType' => ['nullable', 'in:amount,percent'],
            'promoValue' => ['nullable', 'numeric', 'min:0'],
            'dueAt' => ['nullable', 'date'],
        ]);

        if ($invoice->amountsLocked()) {
            $offending = collect($data)
                ->except(['notes', 'dueAt'])
                ->filter(fn ($v) => $v !== null)
                ->keys();

            if ($offending->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'amount' => 'Payments are recorded against this invoice — amounts are locked. Only notes and due date can change.',
                ]);
            }
        }

        $invoice = DocumentIssuer::updateInvoice($invoice, $data);
        $invoice->logActivity('invoice.updated', [
            'invoice_number' => $invoice->invoice_number,
            'fields' => array_keys($data),
        ]);

        $invoice->load(['order.client', 'order.quotation', 'payments']);

        return new InvoiceResource($invoice);
    }

    /**
     * Queue the invoice email (summary + PDF link, PDF attached best-effort).
     * The recipient is whatever the admin typed — used for this send only,
     * never written back to the client record.
     */
    public function send(Request $request, Invoice $invoice): JsonResponse
    {
        abort_if($invoice->status === 'void', 409, 'Void invoices cannot be emailed.');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        SendInvoiceEmail::dispatch($invoice->id, $data['email'], $data['name'] ?? null);
        $invoice->logActivity('invoice.email_queued', ['to' => $data['email']]);

        return response()->json(['queued' => true, 'to' => $data['email']], 202);
    }
}
