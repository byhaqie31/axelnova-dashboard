<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Inquiry as seen from the /team workspace. Same intake data the cockpit sees,
 * minus the sales-pipeline linkage: the quotation reference is omitted because
 * pricing/quotations are a cockpit-only surface (see the permission matrix in
 * DASHBOARD-REVAMP-PLAN §4). Team members triage and respond, they don't price.
 */
class InquiryTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detailRoute = $request->routeIs('team.inquiries.show');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'project_type' => $this->project_type,
            'budget_hint' => $this->budget_hint,
            'timeline_hint' => $this->timeline_hint,
            'message' => $this->when($detailRoute, $this->message),
            'source' => $this->source,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
