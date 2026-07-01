<?php

namespace App\Http\Controllers\Api\V1\Team;

use App\Http\Controllers\Controller;
use App\Http\Resources\InquiryTeamResource;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Inquiry triage for the /team workspace. The read/filter/status logic mirrors the
 * cockpit Admin\InquiriesController; the differences are deliberate and scoped:
 * responses use InquiryTeamResource (no quotation linkage) and the status set
 * excludes 'quoted' — moving an inquiry to quoted happens by building a quotation,
 * which is cockpit-only.
 */
class InquiriesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Inquiry::latest('created_at');

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

        return InquiryTeamResource::collection($query->paginate(20));
    }

    public function show(Inquiry $inquiry): InquiryTeamResource
    {
        // Opening a fresh inquiry counts as triage.
        if ($inquiry->status === 'new') {
            $inquiry->update(['status' => 'reviewing']);
        }

        return new InquiryTeamResource($inquiry);
    }

    public function updateStatus(Request $request, Inquiry $inquiry): JsonResponse
    {
        // No 'quoted' here — that transition belongs to the cockpit quote builder.
        $request->validate([
            'status' => ['required', 'in:new,reviewing,archived'],
        ]);

        $from = $inquiry->status;
        $inquiry->update(['status' => $request->status]);
        $inquiry->logActivity('inquiry.status', ['from' => $from, 'to' => $inquiry->status]);

        return response()->json(['message' => 'Status updated.', 'status' => $inquiry->status]);
    }
}
