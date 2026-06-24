<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Quoting\DocumentIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrdersController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::with(['client', 'quotation'])->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($c) use ($search) {
                      $c->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('quotation', function ($c) use ($search) {
                      $c->where('reference_code', 'like', "%{$search}%");
                  });
            });
        }

        return OrderResource::collection($query->paginate(20));
    }

    public function show(Order $order): OrderResource
    {
        $order->load(['client', 'quotation.addons', 'invoices', 'receipts.invoice']);

        return new OrderResource($order);
    }

    /**
     * Money roll-up across the active book (everything not cancelled), summed
     * in SQL so the dashboard never has to page through orders client-side.
     */
    public function stats(): JsonResponse
    {
        $row = Order::whereNot('status', 'cancelled')
            ->selectRaw('COUNT(*) as active_count')
            ->selectRaw('COALESCE(SUM(final_amount_myr), 0) as revenue')
            ->selectRaw('COALESCE(SUM(amount_paid_myr), 0) as collected')
            ->selectRaw('COALESCE(SUM(GREATEST(final_amount_myr - amount_paid_myr, 0)), 0) as pending')
            ->first();

        return response()->json([
            'active_count' => (int) $row->active_count,
            'revenue' => round((float) $row->revenue, 2),
            'collected' => round((float) $row->collected, 2),
            'pending' => round((float) $row->pending, 2),
        ]);
    }

    /**
     * Adjust the agreed total and record payments. Paid is clamped to
     * [0, final] so the derived remaining balance can never go negative.
     */
    public function updatePayment(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'final_amount_myr' => ['nullable', 'numeric', 'min:0'],
            'amount_paid_myr' => ['nullable', 'numeric', 'min:0'],
            'deposit_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $final = array_key_exists('final_amount_myr', $data) && $data['final_amount_myr'] !== null
            ? (float) $data['final_amount_myr']
            : (float) $order->final_amount_myr;

        $paid = array_key_exists('amount_paid_myr', $data) && $data['amount_paid_myr'] !== null
            ? (float) $data['amount_paid_myr']
            : (float) $order->amount_paid_myr;

        $order->update([
            'final_amount_myr' => $final,
            'amount_paid_myr' => min(max($paid, 0), $final),
            'deposit_pct' => $data['deposit_pct'] ?? $order->deposit_pct,
        ]);

        $order->load(['client', 'quotation']);

        return response()->json([
            'message' => 'Payment details updated.',
            'order' => new OrderResource($order),
        ]);
    }

    /** Set or clear the expected completion date (the order's SLA / deadline). */
    public function updateSchedule(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'due_at' => ['nullable', 'date'],
        ]);

        $order->update(['due_at' => $data['due_at'] ?? null]);
        $order->load(['client', 'quotation']);

        return response()->json([
            'message' => 'Expected completion updated.',
            'order' => new OrderResource($order),
        ]);
    }

    /**
     * Issue an invoice or receipt for the order. Freezes a DocumentData snapshot
     * (see DocumentIssuer) and assigns a derived number (INV-/RCP- + quote ref).
     * An invoice is a deposit / partial / final bill; recording a payment on it
     * accrues onto the order. A receipt confirms a settled payment (and may tie
     * to the invoice it settles).
     */
    public function issueDocument(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:invoice,receipt'],
            'invoiceType' => ['nullable', 'in:deposit,partial,final'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'amountPaid' => ['nullable', 'numeric', 'min:0'],
            'paymentRef' => ['nullable', 'string', 'max:120'],
            'paymentMethod' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:issued,paid,void'],
            // Billing-time reductions off the agreed value: a negotiated discount
            // and/or a promo code, each a fixed amount or a percentage.
            'discountType' => ['nullable', 'in:amount,percent'],
            'discountValue' => ['nullable', 'numeric', 'min:0'],
            'discountLabel' => ['nullable', 'string', 'max:60'],
            'promoCode' => ['nullable', 'string', 'max:40'],
            'promoType' => ['nullable', 'in:amount,percent'],
            'promoValue' => ['nullable', 'numeric', 'min:0'],
            // Optional full DocumentData override from a customized builder.
            'payload' => ['nullable', 'array'],
        ]);

        $order->loadMissing('quotation');

        $document = $data['type'] === 'invoice'
            ? DocumentIssuer::issueInvoice($order, $data)
            : DocumentIssuer::issueReceipt($order, $data);

        $order->load(['client', 'quotation.addons', 'invoices', 'receipts.invoice']);

        return response()->json([
            'message' => ucfirst($data['type']).' issued.',
            'document' => $document,
            'order' => new OrderResource($order),
        ], 201);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:pending,in_progress,delivered,completed,cancelled'],
        ]);

        $next = $request->status;
        $updates = ['status' => $next];

        // Stamp the relevant timestamp on first transition into each phase.
        if ($next === 'in_progress' && !$order->started_at) {
            $updates['started_at'] = now();
        }
        if ($next === 'delivered' && !$order->delivered_at) {
            $updates['delivered_at'] = now();
        }
        if ($next === 'completed' && !$order->completed_at) {
            $updates['completed_at'] = now();
        }

        $order->update($updates);
        $order->load(['client', 'quotation']);

        return response()->json([
            'message' => 'Order status updated.',
            'order' => new OrderResource($order),
        ]);
    }
}
