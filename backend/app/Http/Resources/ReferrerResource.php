<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A referral partner (affiliate account) for the cockpit. Carries programme config +
 * lifecycle, never the passcode hash. `has_passcode` lets staff see whether a
 * passcode has been issued — so they know when a reset is needed — without ever
 * revealing it. Commission is derived at display time, never stored here.
 */
class ReferrerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'relationship_tier' => $this->relationship_tier,
            'commission_pct' => $this->commission_pct,
            'status' => $this->status,
            'agreed_terms' => $this->agreed_terms,
            // Task 9: credentials live on the linked external account.
            'has_passcode' => filled($this->account?->password),
            'referrals_count' => $this->whenCounted('referrals'),
            'last_login_at' => $this->account?->last_login_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
