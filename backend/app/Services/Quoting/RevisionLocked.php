<?php

namespace App\Services\Quoting;

use Illuminate\Http\JsonResponse;

/**
 * A revision-lifecycle rule refused the action (edit a frozen revision, open a
 * second revision, accept a stale one, …). Renders as a 422 whose message names
 * the revision and the reason, so the admin UI and the MCP connector (which
 * passes Laravel messages through verbatim) can both act on it.
 */
class RevisionLocked extends \DomainException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
