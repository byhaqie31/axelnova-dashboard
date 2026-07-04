<?php

namespace App\Http\Resources;

use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferrerDetailResource extends JsonResource
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
            'commission_tiers' => Referral::COMMISSION_TIERS,
            'status' => $this->status,
            // Task 9: credentials live on the linked external account.
            'has_passcode' => filled($this->account?->password),
            'last_login_at' => $this->account?->last_login_at?->toISOString(),
            'stats' => $this->additional['stats'] ?? null,
            'referrals' => ReferralResource::collection($this->whenLoaded('referrals')),
        ];
    }
}
