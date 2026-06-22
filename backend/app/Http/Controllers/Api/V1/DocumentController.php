<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Services\Quoting\DocumentMapper;
use Illuminate\Http\JsonResponse;

class DocumentController extends Controller
{
    /**
     * Public, token-gated document data for the PDF renderer (Nuxt Nitro route).
     * The 48-char random token is the only credential — unguessable, shared with
     * the client via the quotation email link.
     */
    public function show(string $token): JsonResponse
    {
        $quotation = Quotation::with('pricingConfig', 'addons')
            ->where('public_token', $token)
            ->firstOrFail();

        return response()->json(DocumentMapper::toDocumentData($quotation));
    }
}
