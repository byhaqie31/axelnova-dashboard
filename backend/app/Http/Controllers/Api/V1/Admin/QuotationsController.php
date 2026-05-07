<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuotationResource;
use App\Models\QuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuotationsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = QuoteRequest::with('addons')->latest('submitted_at');

        // Quotations view excludes converted by default — converted rows live on Orders.
        if (!$request->boolean('include_converted') && !$request->filled('status')) {
            $query->where('status', '!=', 'converted');
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

    public function show(QuoteRequest $quotation): QuotationResource
    {
        $quotation->load('addons');

        if (!$quotation->viewed_at) {
            $quotation->update([
                'viewed_at' => now(),
                'status' => $quotation->status === 'new' ? 'viewed' : $quotation->status,
            ]);
        }

        return new QuotationResource($quotation);
    }

    public function updateStatus(Request $request, QuoteRequest $quotation): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:new,viewed,contacted,rejected,spam'],
        ]);

        $quotation->update(['status' => $request->status]);

        return response()->json(['message' => 'Status updated.', 'status' => $quotation->status]);
    }

    public function convert(Request $request, QuoteRequest $quotation): JsonResponse
    {
        if ($quotation->status === 'converted') {
            return response()->json(['message' => 'Already converted.'], 422);
        }

        $quotation->update([
            'status' => 'converted',
            'project_status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Quotation converted to order.',
            'quotation' => new QuotationResource($quotation),
        ]);
    }
}
