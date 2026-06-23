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
        $order->load(['client', 'quotation.addons', 'documents']);

        return new OrderResource($order);
    }

    /**
     * Issue an invoice or receipt for the order. Freezes a DocumentData snapshot
     * (see DocumentIssuer) and assigns a derived number (INV-/RCP- + quote ref).
     * Generate the invoice once a deposit/full payment lands; the receipt on
     * full payment.
     */
    public function issueDocument(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:invoice,receipt'],
            'layout' => ['nullable', 'in:standard,detailed'],
            'amountPaid' => ['nullable', 'numeric', 'min:0'],
            'paymentRef' => ['nullable', 'string', 'max:120'],
            'paymentMethod' => ['nullable', 'string', 'max:120'],
            'statusLabel' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'in:issued,paid,void'],
            // Optional full DocumentData override from a customized builder.
            'payload' => ['nullable', 'array'],
        ]);

        $order->loadMissing('quotation');
        $document = DocumentIssuer::issue($order, $data['type'], $data);

        return response()->json([
            'message' => ucfirst($data['type']).' issued.',
            'document' => $document,
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
