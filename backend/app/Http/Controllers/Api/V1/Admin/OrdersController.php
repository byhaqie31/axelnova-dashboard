<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuotationResource;
use App\Models\QuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrdersController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = QuoteRequest::with('addons')
            ->where('status', 'converted')
            ->latest('submitted_at');

        if ($request->filled('project_status')) {
            $query->where('project_status', $request->project_status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('reference_code', 'like', "%{$search}%");
            });
        }

        return QuotationResource::collection($query->paginate(20));
    }

    public function show(QuoteRequest $order): QuotationResource
    {
        abort_if($order->status !== 'converted', 404);

        $order->load('addons');

        return new QuotationResource($order);
    }

    public function updateProjectStatus(Request $request, QuoteRequest $order): JsonResponse
    {
        abort_if($order->status !== 'converted', 404);

        $request->validate([
            'project_status' => ['required', 'in:pending,in_progress,delivered,completed'],
        ]);

        $next = $request->project_status;
        $updates = ['project_status' => $next];

        // Stamp the relevant timestamp on first transition into each phase.
        if ($next === 'in_progress' && !$order->project_started_at) {
            $updates['project_started_at'] = now();
        }
        if ($next === 'delivered' && !$order->project_delivered_at) {
            $updates['project_delivered_at'] = now();
        }
        if ($next === 'completed' && !$order->project_completed_at) {
            $updates['project_completed_at'] = now();
        }

        $order->update($updates);

        return response()->json([
            'message' => 'Project status updated.',
            'order' => new QuotationResource($order),
        ]);
    }
}
