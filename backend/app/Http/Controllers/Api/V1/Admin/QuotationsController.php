<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeadResource;
use App\Models\QuoteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeadsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = QuoteRequest::with('addons')->latest('submitted_at');

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

        return LeadResource::collection($query->paginate(20));
    }

    public function show(QuoteRequest $lead): LeadResource
    {
        $lead->load('addons');

        if (!$lead->viewed_at) {
            $lead->update([
                'viewed_at' => now(),
                'status' => $lead->status === 'new' ? 'viewed' : $lead->status,
            ]);
        }

        return new LeadResource($lead);
    }

    public function updateStatus(Request $request, QuoteRequest $lead): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:new,viewed,contacted,converted,rejected,spam'],
        ]);

        $lead->update(['status' => $request->status]);

        return response()->json(['message' => 'Status updated.', 'status' => $lead->status]);
    }

    public function convert(Request $request, QuoteRequest $lead): JsonResponse
    {
        if ($lead->status === 'converted') {
            return response()->json(['message' => 'Already converted.'], 422);
        }

        // Phase 3: mark as converted — full Client + Quotation creation is Phase 4
        $lead->update(['status' => 'converted']);

        return response()->json([
            'message' => 'Lead marked as converted. Full client + quotation creation is Phase 4.',
            'lead' => new LeadResource($lead),
        ]);
    }
}
