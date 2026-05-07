<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
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
        $order->load(['client', 'quotation.addons']);

        return new OrderResource($order);
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
