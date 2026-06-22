<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInquiryRequest;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;

class InquiryController extends Controller
{
    public function store(StoreInquiryRequest $request): JsonResponse
    {
        $inquiry = Inquiry::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'company' => $request->input('company'),
            'project_type' => $request->input('project_type'),
            'budget_hint' => $request->input('budget_hint'),
            'timeline_hint' => $request->input('timeline_hint'),
            'message' => $request->input('message'),
            'source' => 'web',
            'status' => 'new',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'data' => ['id' => $inquiry->id],
            'message' => 'Inquiry received. I\'ll review the details and get back to you shortly.',
        ], 201);
    }
}
