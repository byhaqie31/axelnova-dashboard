<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Cross-order invoices — the standalone Invoices module. Read-only here; invoices
 * are issued from the order detail (DocumentIssuer) and their paid state is
 * observer-maintained.
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
}
