<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** A project as listed under a studio partner: the usual project shape plus how it was built together. */
class CollaborationProjectResource extends ProjectResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'collaboration' => [
                'mode' => $this->pivot->mode,
                'role_note' => $this->pivot->role_note,
            ],
        ];
    }
}
