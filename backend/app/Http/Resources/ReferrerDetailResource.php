<?php

namespace App\Http\Resources;

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
            'commission_tiers' => \App\Models\Referral::COMMISSION_TIERS,
            'status' => $this->status,
            'has_passcode' => filled($this->password),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'stats' => $this->additional['stats'] ?? null,
            'referrals' => ReferralResource::collection($this->whenLoaded('referrals')),
        ];
    }
}
