<?php

namespace App\Http\Controllers\Api\V1\Connector;

use App\Http\Controllers\Controller;
use App\Http\Requests\Connector\DraftQuotationRequest;
use App\Models\Client;
use App\Models\Quotation;
use App\Services\Connector\ConnectorCatalog;
use App\Services\Quoting\DocumentSeeder;
use App\Services\Quoting\PricingEngine;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     * Create a DRAFT quotation from a client brief. Two paths share this method:
     *
     *   • Priced   — package_key set: re-priced through the SAME PricingEngine as
     *     the public funnel (modifiers/scope fields/add-ons/rush). Any line_items
     *     ride along as extras in the document, never added to the engine price.
     *   • Bespoke  — package_key null: estimate_min == estimate_max == Σ line_items;
     *     ETA left for the admin (stored as the 0/'week' "no ETA" sentinel).
     *
     * Always lands as status=draft, source=admin, with an AXNQ reference code. It
     * NEVER sends anything to the client — the admin reviews and delivers.
     */
    public function store(DraftQuotationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $catalog = new ConnectorCatalog;
        $engine = PricingEngine::active();

        $rawPackages = $this->rawPackages($data);
        $rush = (bool) ($data['rush'] ?? false);
        $lineItems = $this->normaliseLineItems($data['line_items'] ?? []);

        $quotation = DB::transaction(function () use ($request, $data, $catalog, $engine, $rawPackages, $rush, $lineItems) {
            // Upsert the client by email — same dedup pattern as the public funnel.
            $client = Client::firstOrCreate(
                ['email' => $data['client']['email']],
                [
                    'name' => $data['client']['name'],
                    'phone' => $data['client']['phone'] ?? null,
                    'company' => $data['client']['company'] ?? null,
                ],
            );

            // Resolve to the canonical packages[] the whole system reads: split each
            // package's flat `modifiers` map onto the engine's modifiers/scope_values
            // and resolve its service_package_id.
            $packages = array_map(function (array $rp) use ($catalog, $engine): array {
                $key = (string) $rp['package_key'];
                $split = $catalog->splitModifiers($key, (array) ($rp['modifiers'] ?? []));

                return [
                    'package_key' => $key,
                    'service_package_id' => $engine->packageId($key),
                    'scope_values' => $split['scope_values'],
                    'modifiers' => $split['modifiers'],
                    'addon_keys' => array_values((array) ($rp['addon_keys'] ?? [])),
                ];
            }, $rawPackages);

            $isBespoke = $packages === [];
            $estimate = $engine->calculateMulti($packages, $rush);

            if ($isBespoke) {
                // Bespoke: the sum of the line items IS the agreed range; ETA left as
                // the 0/'week' "no ETA yet" sentinel (columns are NOT NULL).
                $minMyr = $maxMyr = array_sum(array_column($lineItems, 'amount_myr'));
                $etaValue = 0;
                $etaUnit = 'week';
            } else {
                $minMyr = $estimate->minMyr;
                $maxMyr = $estimate->maxMyr;
                $etaValue = $estimate->etaValue;
                $etaUnit = $estimate->etaUnit;
            }

            // Seed the canonical document (document.items — what the PDF renders). A
            // fresh connector row always seeds; line_items ride along as extra lines.
            // Bespoke never applies a rush uplift (its total is the line-item sum).
            $seeded = (new DocumentSeeder($engine))->seed($estimate, ! $isBespoke && $rush, $lineItems);
            $document = array_merge($seeded['document'], [
                'created_via' => 'mcp_connector',
                'assumptions' => array_values(array_merge($data['assumptions'] ?? [], $seeded['assumptions'])),
                'open_questions' => array_values($data['open_questions'] ?? []),
                'notes' => $data['notes'] ?? null,
            ]);

            $first = $packages[0] ?? null;

            $quotation = Quotation::create([
                'reference_code' => ReferenceCodeGenerator::generate(DocumentType::Quotation),
                'source' => 'admin',
                'status' => 'draft',
                'public_token' => Str::random(48),
                'client_id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'company' => $client->company,
                'package_key' => $first['package_key'] ?? null,
                'service_package_id' => $first['service_package_id'] ?? null,
                'pricing_config_id' => $engine->getConfig()->id,
                // Canonical multi-package form_payload (see FormPayloadNormalizer) +
                // the full request body as an audit trail, so the admin can reopen
                // and re-price the draft in the quote builder.
                'form_payload' => [
                    'request' => $request->all(),
                    'packages' => $packages,
                    'rush' => $rush,
                    'breakdown' => $estimate->breakdown,
                    'source_meta' => ['created_via' => 'mcp_connector'],
                ],
                'document' => $document,
                'estimate_min_myr' => $minMyr,
                'estimate_max_myr' => $maxMyr,
                'estimate_eta_value' => $etaValue,
                'estimate_eta_unit' => $etaUnit,
                'submitted_at' => now(),
            ]);

            // Persist add-on rows (union across packages) so the admin detail + PDF
            // show them.
            $addonKeys = collect($packages)->flatMap(fn (array $p): array => $p['addon_keys'])->unique()->values()->all();
            if ($addonKeys !== []) {
                $addonDefs = $engine->addons();
                foreach ($addonKeys as $key) {
                    if (isset($addonDefs[$key])) {
                        $quotation->addons()->create([
                            'addon_key' => $key,
                            'addon_label' => $addonDefs[$key]['label'],
                            'amount_myr' => $addonDefs[$key]['amount'],
                        ]);
                    }
                }
            }

            return $quotation;
        });

        $quotation->logActivity('quotation.created', [
            'reference_code' => $quotation->reference_code,
            'via' => 'mcp_connector',
        ]);

        return response()->json([
            'message' => "Draft quotation {$quotation->reference_code} created. It is a DRAFT for admin review — nothing has been sent to the client.",
            'data' => self::connectorView($quotation->load('addons')),
        ], 201);
    }

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
            'line_items' => self::lineItemsView($document),
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

    /**
     * Coerce the request's line items to a stable shape. Used for both the bespoke
     * total and the extras seeded onto a priced draft's document.
     *
     * @return list<array{label: string, description: ?string, amount_myr: float}>
     */
    private function normaliseLineItems(array $items): array
    {
        return array_values(array_map(fn (array $item): array => [
            'label' => (string) $item['label'],
            'description' => $item['description'] ?? null,
            'amount_myr' => (float) $item['amount_myr'],
        ], $items));
    }

    /**
     * Normalise the request into a list of raw package entries: the canonical
     * packages[] when present, else the single top-level package (sugar), else []
     * for a fully bespoke quote.
     *
     * @return list<array{package_key: string, modifiers: array, addon_keys: list<string>}>
     */
    private function rawPackages(array $data): array
    {
        if (! empty($data['packages']) && is_array($data['packages'])) {
            return array_values(array_map(fn (array $p): array => [
                'package_key' => (string) ($p['package_key'] ?? ''),
                'modifiers' => (array) ($p['modifiers'] ?? []),
                'addon_keys' => array_values((array) ($p['addon_keys'] ?? [])),
            ], array_filter($data['packages'], 'is_array')));
        }

        $key = ($data['package_key'] ?? null) ?: null;
        if ($key === null) {
            return [];
        }

        return [[
            'package_key' => (string) $key,
            'modifiers' => (array) ($data['modifiers'] ?? []),
            'addon_keys' => array_values((array) ($data['addon_keys'] ?? [])),
        ]];
    }

    /**
     * The connector-facing line_items view. Reads the canonical document.items
     * (what the PDF renders), falling back to the legacy connector document.line_items
     * for rows created before the shape unified.
     *
     * @param  array<string, mixed>  $document
     * @return list<array{label: string, description: ?string, amount_myr: float}>
     */
    private static function lineItemsView(array $document): array
    {
        if (! empty($document['items']) && is_array($document['items'])) {
            return array_values(array_map(fn (array $it): array => [
                'label' => (string) ($it['title'] ?? 'Item'),
                'description' => $it['desc'] ?? null,
                'amount_myr' => (float) ($it['rate'] ?? 0) * (float) ($it['qty'] ?? 1),
            ], $document['items']));
        }

        return array_values(array_map(fn (array $it): array => [
            'label' => (string) ($it['label'] ?? 'Item'),
            'description' => $it['description'] ?? null,
            'amount_myr' => (float) ($it['amount_myr'] ?? 0),
        ], $document['line_items'] ?? []));
    }
}
