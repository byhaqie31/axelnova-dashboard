<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInquiryRequest;
use App\Mail\InquiryAdminNotificationMail;
use App\Mail\InquiryReceivedMail;
use App\Models\Inquiry;
use App\Models\Referrer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class InquiryController extends Controller
{
    public function store(StoreInquiryRequest $request): JsonResponse
    {
        // No client row yet — /quote and /contact both land here, and most contact
        // messages never become business. The client is created when the admin
        // quotes the inquiry (QuotationsController@store / linkQuotation stamp it back).

        // Attribution: the axn_ref cookie carries the first-touch referrer code
        // (functional cookie, set only after consent); if consent was declined the
        // ?ref param may still ride the submit for best-effort attribution. Resolve
        // to a referrer → source = 'referral'; otherwise it's public/organic.
        $referrer = $this->resolveReferrer($request);

        $inquiry = Inquiry::create([
            'referral_partner_id' => $referrer?->id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'company' => $request->input('company'),
            'project_type' => $request->input('project_type'),
            'budget_hint' => $request->input('budget_hint'),
            'timeline_hint' => $request->input('timeline_hint'),
            'message' => $request->input('message'),
            'source' => $referrer ? 'referral' : 'web',
            'origin' => $request->input('origin', 'quote'),
            'subject' => $request->input('subject'),
            'status' => 'new',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Queued acknowledgement back to the person who inquired.
        Mail::to($inquiry->email, $inquiry->name)->send(new InquiryReceivedMail($inquiry));
        // Queued heads-up to the admin inbox (replaces the old browser-side Web3Forms ping).
        // Delayed like NotifyAdminJob so the two sends don't hit the SMTP per-second cap.
        Mail::to(config('services.admin.email'))
            ->later(now()->addSeconds(10), new InquiryAdminNotificationMail($inquiry));

        return response()->json([
            'data' => ['id' => $inquiry->id],
            'message' => 'Inquiry received. I\'ll review the details and get back to you shortly.',
        ], 201);
    }

    /**
     * Resolve the attributed referrer from the axn_ref cookie, falling back to a
     * ?ref query param. Only active referrers earn attribution — a pending/paused
     * code is ignored. Returns null for public/organic traffic.
     */
    private function resolveReferrer(StoreInquiryRequest $request): ?Referrer
    {
        $code = $request->cookie('axn_ref') ?: $request->query('ref');

        if (! $code) {
            return null;
        }

        return Referrer::where('code', $code)->where('status', 'active')->first();
    }
}
