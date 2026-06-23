<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Quotation;
use App\Services\Quoting\DocumentMapper;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentController extends Controller
{
    /**
     * Public, token-gated document data for the PDF renderer (Nuxt Nitro route).
     * The random token is the only credential — unguessable, shared via the
     * document link.
     *
     * Issued invoices/receipts return their FROZEN payload (never recomputed);
     * quotations are mapped live from the current row.
     */
    public function show(string $token): JsonResponse
    {
        $document = Document::where('public_token', $token)->first();
        if ($document) {
            return response()->json($document->payload);
        }

        $quotation = Quotation::with('pricingConfig', 'addons')
            ->where('public_token', $token)
            ->first();
        if ($quotation) {
            return response()->json(DocumentMapper::toDocumentData($quotation));
        }

        throw new NotFoundHttpException('Document not found');
    }
}
