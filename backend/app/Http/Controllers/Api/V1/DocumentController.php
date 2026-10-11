<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\Receipt;
use App\Services\Quoting\DocumentMapper;
use App\Services\Quoting\QuotationRevisions;
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
        $invoice = Invoice::where('public_token', $token)->first();
        if ($invoice) {
            return response()->json($invoice->payload);
        }

        $receipt = Receipt::where('public_token', $token)->first();
        if ($receipt) {
            return response()->json($receipt->payload);
        }

        // Legacy combined documents (pre-split) still resolve by token.
        $document = Document::where('public_token', $token)->first();
        if ($document) {
            return response()->json($document->payload);
        }

        // A quotation revision — each revision has its own token; R1 inherited the
        // family's original token, so every link sent before revisions existed
        // still opens the same document. Mapped live from the revision's stored
        // content (accepted / superseded revisions are never re-priced).
        $revision = QuotationRevision::with('quotation.pricingConfig', 'pricingConfig')
            ->where('public_token', $token)
            ->first();
        if ($revision && $revision->quotation && ! $revision->quotation->trashed()) {
            // A client opening an overdue quote also triggers lazy expiry.
            if ($revision->status === QuotationRevision::SENT && $revision->expires_at?->isPast()) {
                QuotationRevisions::expire($revision);
            }

            return response()->json(DocumentMapper::toDocumentData($revision->asQuotation()));
        }

        // Families whose token predates their revisions (safety net — the
        // migration copied every token onto R1).
        $quotation = Quotation::with('pricingConfig', 'addons')
            ->where('public_token', $token)
            ->first();
        if ($quotation) {
            return response()->json(DocumentMapper::toDocumentData($quotation->revision()->asQuotation($quotation)));
        }

        throw new NotFoundHttpException('Document not found');
    }
}
