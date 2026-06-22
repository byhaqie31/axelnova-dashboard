<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInquiryRequest;
use App\Mail\InquiryReceivedMail;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

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

        // Queued acknowledgement back to the person who inquired.
        Mail::to($inquiry->email, $inquiry->name)->send(new InquiryReceivedMail($inquiry));

        return response()->json([
            'data' => ['id' => $inquiry->id],
            'message' => 'Inquiry received. I\'ll review the details and get back to you shortly.',
        ], 201);
    }
}
