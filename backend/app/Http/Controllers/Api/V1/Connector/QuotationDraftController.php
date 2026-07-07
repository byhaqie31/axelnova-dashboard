<?php

namespace App\Http\Controllers\Api\V1\Connector;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Illuminate\Http\JsonResponse;

/**
 * The MCP connector's draft-quotation surface. Reads back (connector:read) and
 * creates (connector:draft) DRAFT quotations only — it can never change status,
 * accept a quote, or touch orders/clients/payments. Draft creation logic lives
 * in store(); the read-back is scoped to connector-created rows so the token
 * can't enumerate arbitrary quotations.
 */
class QuotationDraftController extends Controller
{
    /**
     * Read back a connector-created draft by its AXNQ reference code. Scoped to
     * rows this connector authored (document.created_via = mcp_connector) so the
     * read ability can't fan out across every quotation in the system.
     */
    public function show(string $reference_code): JsonResponse
    {
        $quotation = Quotation::query()
            ->where('reference_code', $reference_code)
            ->where('document->created_via', 'mcp_connector')
            ->with('addons')
            ->first();

        if (! $quotation) {
            return response()->json([
                'message' => "No connector-created quotation found with reference_code '{$reference_code}'. "
                    .'Only quotations created via this connector are readable here; use the exact AXNQ code returned by create_draft_quotation.',
            ], 404);
        }

        return response()->json(['data' => self::connectorView($quotation)]);
    }

    /**
     * The connector-facing projection of a quotation — the draft as the AI (and
     * the admin reviewing it) needs to see it. Kept deliberately narrow: no
     * public_token, no internal audit fields.
     */
    protected static function connectorView(Quotation $quotation): array
    {
        $document = is_array($quotation->document) ? $quotation->document : [];
        $hasEta = (int) $quotation->estimate_eta_value > 0;

        return [
            'reference_code' => $quotation->reference_code,
            'status' => $quotation->status,
            'source' => $quotation->source,
            'created_via' => $document['created_via'] ?? null,
            'client' => [
                'name' => $quotation->name,
                'email' => $quotation->email,
                'phone' => $quotation->phone,
                'company' => $quotation->company,
            ],
            'package_key' => $quotation->package_key,
            'estimate' => [
                'min_myr' => (float) $quotation->estimate_min_myr,
                'max_myr' => (float) $quotation->estimate_max_myr,
                'eta_value' => $hasEta ? (int) $quotation->estimate_eta_value : null,
                'eta_unit' => $hasEta ? $quotation->estimate_eta_unit : null,
            ],
            'line_items' => $document['line_items'] ?? [],
            'assumptions' => $document['assumptions'] ?? [],
            'open_questions' => $document['open_questions'] ?? [],
            'notes' => $document['notes'] ?? null,
            'addons' => $quotation->relationLoaded('addons')
                ? $quotation->addons->map(fn ($a): array => [
                    'key' => $a->addon_key,
                    'label' => $a->addon_label,
                    'amount_myr' => (float) $a->amount_myr,
                ])->values()->all()
                : [],
            'admin_url' => rtrim((string) config('services.frontend.url'), '/')."/admin/quotations/{$quotation->id}",
            'created_at' => $quotation->created_at?->toISOString(),
        ];
    }
}
