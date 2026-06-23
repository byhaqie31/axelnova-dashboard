<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\InquiryResource;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InquiriesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Inquiry::with('quotation')->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return InquiryResource::collection($query->paginate(20));
    }

    public function show(Inquiry $inquiry): InquiryResource
    {
        $inquiry->load('quotation');

        if ($inquiry->status === 'new') {
            $inquiry->update(['status' => 'reviewing']);
        }

        return new InquiryResource($inquiry);
    }

    public function updateStatus(Request $request, Inquiry $inquiry): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:new,reviewing,quoted,archived'],
        ]);

        $inquiry->update(['status' => $request->status]);

        return response()->json(['message' => 'Status updated.', 'status' => $inquiry->status]);
    }
}
