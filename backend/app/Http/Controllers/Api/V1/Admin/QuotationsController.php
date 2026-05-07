<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuotationResource;
use App\Models\Order;
use App\Models\Quotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class QuotationsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Quotation::with('addons')->latest('submitted_at');

        // Quotations view excludes 'accepted' by default — accepted ones produced an order
        // and live on the Orders page. Caller can pass ?include_accepted=1 or ?status=accepted.
        if (!$request->boolean('include_accepted') && !$request->filled('status')) {
            $query->where('status', '!=', 'accepted');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('reference_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('submitted_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('submitted_at', '<=', $request->date_to);
        }

        return QuotationResource::collection($query->paginate(20));
    }

    public function show(Quotation $quotation): QuotationResource
    {
        $quotation->load('addons', 'order');

        if (!$quotation->viewed_at) {
            $quotation->update([
                'viewed_at' => now(),
                'status' => $quotation->status === 'new' ? 'viewed' : $quotation->status,
            ]);
        }

        return new QuotationResource($quotation);
    }

    public function updateStatus(Request $request, Quotation $quotation): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:new,viewed,contacted,rejected,spam'],
        ]);

        $quotation->update(['status' => $request->status]);

        return response()->json(['message' => 'Status updated.', 'status' => $quotation->status]);
    }

    public function accept(Request $request, Quotation $quotation): JsonResponse
    {
        if ($quotation->status === 'accepted') {
            return response()->json(['message' => 'Already accepted.', 'order_id' => $quotation->order?->id], 422);
        }

        if (!$quotation->client_id) {
            return response()->json(['message' => 'Quotation has no client linked.'], 422);
        }

        $order = DB::transaction(function () use ($quotation) {
            $quotation->update(['status' => 'accepted']);

            return Order::create([
                'order_number' => $this->generateOrderNumber(),
                'quotation_id' => $quotation->id,
                'client_id' => $quotation->client_id,
                'value_min_myr' => $quotation->estimate_min_myr,
                'value_max_myr' => $quotation->estimate_max_myr,
                'status' => 'pending',
            ]);
        });

        return response()->json([
            'message' => 'Quotation accepted. Order created.',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }

    private function generateOrderNumber(): string
    {
        return DB::transaction(function () {
            $year = date('Y');
            $latest = Order::where('order_number', 'like', "ORD-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('order_number');

            $next = $latest ? ((int) substr($latest, -4)) + 1 : 1;

            return sprintf('ORD-%s-%04d', $year, $next);
        });
    }
}
