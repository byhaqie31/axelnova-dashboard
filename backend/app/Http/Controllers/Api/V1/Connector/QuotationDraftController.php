<?php

namespace App\Http\Controllers\Api\V1\Connector;

use App\Http\Controllers\Controller;
use App\Http\Requests\Connector\DraftQuotationRequest;
use App\Http\Requests\Connector\ListQuotationsRequest;
use App\Http\Requests\Connector\UpdateDraftQuotationRequest;
use App\Models\Client;
use App\Models\Quotation;
use App\Services\Connector\ConnectorCatalog;
use App\Services\Quoting\DetailedDocumentBuilder;
use App\Services\Quoting\DocumentSeeder;
use App\Services\Quoting\PricingEngine;
use App\Services\Quoting\QuotationIndexQuery;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The MCP connector's quotation surface. Access model (v3):
 *   • READ everything  — list (index) and read-back (show) ANY non-deleted
 *     quotation, whatever created it. Soft-deleted rows are never exposed.
 *   • WRITE with a gate — create (store) a DRAFT, and update (update) ANY
 *     quotation while it is pre-send (draft/new/viewed/contacted); locked once sent.
 *   • DESTROY never     — there is no delete here; that is portal-only, by hand.
 *
 * All the shared pricing/document logic lives in buildDraft(); store() creates a
 * new row from it, update() re-prices an existing one (with a document-reseed
 * guard so an admin-edited document is never silently replaced).
 */
class QuotationDraftController extends Controller
{
    /**
     * List quotations (slim rows) — the connector's browse surface. Filters:
     * status[] (any lifecycle value, default all non-deleted), q (name/email/ref
     * search), from/to (created date range), page/per_page (default 10, max 25).
     * Newest first. Full detail comes from show(); this never returns form_payload
     * or the document.
     */
    public function index(ListQuotationsRequest $request): JsonResponse
    {
        // Self-heal overdue sent quotes so 'expired' is accurate + filterable.
        Quotation::expireOverdue();

        $data = $request->validated();
        // Default 10, hard-capped at 25 (silently — a forgiving ceiling, not a 422).
        $perPage = min(25, max(1, (int) ($data['per_page'] ?? 10)));

        // Search + status + date-range + newest-first — the SAME shared query the
        // admin index uses (soft-deleted rows excluded by the model scope).
        $paginator = QuotationIndexQuery::fromConnectorFilters($data)
            ->builder()
            ->paginate($perPage);

        // Resolve the engine once (package-label lookup), not once per row.
        $engine = PricingEngine::active();

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (Quotation $q): array => self::listRow($q, $engine))
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Create a DRAFT quotation from a client brief. Priced (package_key/packages),
     * bespoke (line_items only), or detailed (self-priced sections). Always lands
     * status=draft, source=admin, AXNQ code; it NEVER sends to the client.
     */
    public function store(DraftQuotationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $catalog = new ConnectorCatalog;
        $engine = PricingEngine::active();

        $quotation = DB::transaction(function () use ($request, $data, $catalog, $engine) {
            $client = $this->upsertClient($data['client']);

            $built = $this->buildDraft($request, $data, $client, $catalog, $engine, 'mcp_connector', [
                'created_via' => 'mcp_connector',
            ]);

            $quotation = Quotation::create(array_merge($built['attributes'], [
                'reference_code' => ReferenceCodeGenerator::generate(DocumentType::Quotation),
                'source' => 'admin',
                'status' => 'draft',
                'public_token' => Str::random(48),
                'submitted_at' => now(),
            ]));

            $this->syncAddons($quotation, $built['addonKeys'], $engine);

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
     * Update ANY quotation while it is pre-send (draft/new/viewed/contacted). Once
     * sent (or accepted/declined/rejected/expired/spam) it is locked — refused with
     * a message naming the status. Re-prices the estimate; the document is only
     * re-seeded when it is still a pristine engine-seed (or the caller passes
     * reseed_document: true) so an admin-edited document is never silently replaced.
     */
    public function update(UpdateDraftQuotationRequest $request, string $reference_code): JsonResponse
    {
        $quotation = Quotation::where('reference_code', $reference_code)->first();

        if (! $quotation) {
            return response()->json([
                'message' => "No quotation found with reference_code '{$reference_code}'. Use list_quotations to find it, or the exact AXNQ code.",
            ], 404);
        }

        // Lifecycle gate — pre-send only. A whitelist, so any unknown/future status
        // is treated as locked. The only pre-send status is 'draft' (see
        // Quotation::PRE_SEND_STATUSES).
        if (! $quotation->isPreSend()) {
            return response()->json([
                'message' => "Quotation {$quotation->reference_code} is '{$quotation->status}' and can no longer be updated from the connector — "
                    .'only a pre-send draft (status: '.implode(', ', Quotation::PRE_SEND_STATUSES).') is updatable. '
                    .'Once a quote is sent to the client, a change is a manual admin revision — out of scope here.',
            ], 422);
        }

        $data = $request->validated();
        $catalog = new ConnectorCatalog;
        $engine = PricingEngine::active();
        $reseedFlag = (bool) ($data['reseed_document'] ?? false);

        $existingDoc = is_array($quotation->document) ? $quotation->document : [];
        $existingLayout = $existingDoc['layout'] ?? 'standard';
        $mode = $this->draftMode($data);

        // Reseed decision (locked decision #3): regenerate the document only when
        // it is safe to — an explicit flag, an empty document, or a still-pristine
        // engine seed. Otherwise the admin-edited document is preserved.
        $regenerate = $reseedFlag
            || ! DocumentSeeder::hasContent($existingDoc)
            || $this->isPristineStandardSeed($quotation, $engine);

        // Not regenerating, decided BEFORE any write: we can only safely preserve a
        // standard document while re-pricing the engine estimate. A detailed/bespoke
        // document IS its own price (re-pricing it means replacing it), and a format
        // switch would strand the old document — both need an explicit reseed_document.
        $preserveDocument = ! $regenerate && $mode === 'priced' && $existingLayout === 'standard';

        if (! $regenerate && ! $preserveDocument) {
            return response()->json([
                'message' => "Quotation {$quotation->reference_code} has an edited or detailed document that this update would replace. "
                    .'Re-send with reseed_document: true to regenerate the document from the new scope, '
                    .'or edit it by hand in the admin builder.',
            ], 422);
        }

        // created_via is sticky — updating a funnel/admin draft keeps its origin
        // provenance; we stamp last_updated_via separately.
        $createdVia = $quotation->normalizedForm()['source_meta']['created_via'] ?? null;
        $sourceMeta = [
            'created_via' => $createdVia,
            'last_updated_via' => 'mcp_connector',
            'last_updated_at' => now()->toISOString(),
        ];
        $documentReseeded = $regenerate;

        DB::transaction(function () use ($request, $data, $catalog, $engine, $createdVia, $sourceMeta, $quotation, $existingDoc, $preserveDocument): void {
            $built = $this->buildDraft($request, $data, $this->upsertClient($data['client']), $catalog, $engine, $createdVia, $sourceMeta);
            if ($preserveDocument) {
                $built['attributes']['document'] = $existingDoc;
            }
            $quotation->update($built['attributes']);
            $this->syncAddons($quotation, $built['addonKeys'], $engine);
        });

        $quotation->logActivity('quotation.updated', [
            'via' => 'mcp_connector',
            'document_reseeded' => $documentReseeded,
        ]);

        $note = $documentReseeded
            ? 'The document was re-seeded from the new scope.'
            : 'The existing (edited) document was preserved; the estimate was re-priced. Pass reseed_document: true to regenerate it.';

        return response()->json([
            'message' => "Quotation {$quotation->reference_code} updated. {$note} It remains a DRAFT for admin review.",
            'document_reseeded' => $documentReseeded,
            'data' => self::connectorView($quotation->fresh()->load('addons')),
        ]);
    }

    /**
     * Read back ANY non-deleted quotation by its reference code (v3: reads are
     * open — whatever created the row). Soft-deleted rows return 404 (the model's
     * SoftDeletes scope excludes them).
     */
    public function show(string $reference_code): JsonResponse
    {
        $quotation = Quotation::query()
            ->where('reference_code', $reference_code)
            ->with('addons')
            ->first();

        if (! $quotation) {
            return response()->json([
                'message' => "No quotation found with reference_code '{$reference_code}'. Use list_quotations to browse, or pass the exact AXNQ code.",
            ], 404);
        }

        return response()->json(['data' => self::connectorView($quotation)]);
    }

    /**
     * Price the validated draft data and build the full set of Quotation attributes
     * (document already merged with presentation fields + source_meta). Shared by
     * store() (fresh row) and update() (re-price). Detailed / priced / bespoke are
     * the three modes, mirroring the create contract.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $sourceMeta  form_payload.source_meta to stamp.
     * @return array{attributes: array<string, mixed>, addonKeys: list<string>, mode: string}
     */
    private function buildDraft(FormRequest $request, array $data, Client $client, ConnectorCatalog $catalog, PricingEngine $engine, ?string $createdVia, array $sourceMeta): array
    {
        $rush = (bool) ($data['rush'] ?? false);
        $lineItems = $this->normaliseLineItems($data['line_items'] ?? []);

        $presentation = [
            'project' => ($data['project'] ?? null) ?: null,
            'intro' => ($data['intro'] ?? null) ?: null,
            'created_via' => $createdVia,
            'open_questions' => array_values($data['open_questions'] ?? []),
            'notes' => $data['notes'] ?? null,
        ];

        if (! empty($data['detailed']) && is_array($data['detailed'])) {
            // Detailed proposal — self-priced from its own sections (no engine).
            $result = (new DetailedDocumentBuilder)->build(
                $data['detailed'],
                $presentation['project'],
                $presentation['intro'],
            );
            $document = array_merge($result['document'], $presentation, [
                'assumptions' => array_values($data['assumptions'] ?? []),
            ]);
            $minMyr = $maxMyr = $result['total'];
            $etaValue = 0;
            $etaUnit = 'week';
            $packages = [];
            $first = null;
            $breakdown = [];
            $mode = 'detailed';
        } else {
            $packages = $this->resolveConnectorPackages($this->rawPackages($data), $catalog, $engine);
            $isBespoke = $packages === [];
            $estimate = $engine->calculateMulti($packages, $rush);

            if ($isBespoke) {
                $minMyr = $maxMyr = array_sum(array_column($lineItems, 'amount_myr'));
                $etaValue = 0;
                $etaUnit = 'week';
                $mode = 'bespoke';
            } else {
                $minMyr = $estimate->minMyr;
                $maxMyr = $estimate->maxMyr;
                $etaValue = $estimate->etaValue;
                $etaUnit = $estimate->etaUnit;
                $mode = 'priced';
            }

            // Bespoke never applies a rush uplift (its total is the line-item sum).
            $seeded = (new DocumentSeeder($engine))->seed($estimate, ! $isBespoke && $rush, $lineItems);
            $document = array_merge($seeded['document'], $presentation, [
                'assumptions' => array_values(array_merge($data['assumptions'] ?? [], $seeded['assumptions'])),
            ]);
            $first = $packages[0] ?? null;
            $breakdown = $estimate->breakdown;
        }

        $attributes = [
            'client_id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'company' => $client->company,
            'package_key' => $first['package_key'] ?? null,
            'service_package_id' => $first['service_package_id'] ?? null,
            'pricing_config_id' => $engine->getConfig()->id,
            'form_payload' => [
                // Full request body as an audit trail (also the source of the
                // line_items the pristine-seed check re-derives from).
                'request' => $request->all(),
                'packages' => $packages,
                'rush' => $rush,
                'breakdown' => $breakdown,
                'source_meta' => $sourceMeta,
            ],
            'document' => $document,
            'estimate_min_myr' => $minMyr,
            'estimate_max_myr' => $maxMyr,
            'estimate_eta_value' => $etaValue,
            'estimate_eta_unit' => $etaUnit,
        ];

        $addonKeys = collect($packages)
            ->flatMap(fn (array $p): array => $p['addon_keys'])
            ->unique()
            ->values()
            ->all();

        return ['attributes' => $attributes, 'addonKeys' => $addonKeys, 'mode' => $mode];
    }

    /**
     * Whether the stored document is still exactly what the seeder would produce
     * from the row's CURRENT form_payload — i.e. a connector-seeded standard
     * document that no admin has hand-edited. Recompute-and-compare, so it needs no
     * stored marker and stays false for detailed/bespoke/edited documents (which
     * are then preserved). If the active pricing config changed since seeding, the
     * recompute won't match and we conservatively treat it as edited.
     */
    private function isPristineStandardSeed(Quotation $quotation, PricingEngine $engine): bool
    {
        $doc = is_array($quotation->document) ? $quotation->document : [];
        if (($doc['layout'] ?? 'standard') !== 'standard') {
            return false;
        }
        if (empty($doc['items']) || ! is_array($doc['items'])) {
            return false;
        }

        $form = $quotation->normalizedForm();
        $packages = $form['packages'];
        if ($packages === []) {
            return false; // bespoke-shaped — no standard seed to compare against.
        }

        $rush = (bool) $form['rush'];
        $lineItems = $this->normaliseLineItems($quotation->form_payload['request']['line_items'] ?? []);

        $expected = (new DocumentSeeder($engine))
            ->seed($engine->calculateMulti($packages, $rush), $rush, $lineItems)['document'];

        return self::itemsSignature($expected['items'] ?? []) === self::itemsSignature($doc['items'] ?? []);
    }

    /** A stable hash of a document's line items, tolerant of key order. */
    private static function itemsSignature(array $items): string
    {
        $normalised = array_map(fn (array $it): array => [
            'title' => (string) ($it['title'] ?? ''),
            'desc' => (string) ($it['desc'] ?? ''),
            'qty' => (float) ($it['qty'] ?? 0),
            'unit' => (string) ($it['unit'] ?? ''),
            'rate' => (float) ($it['rate'] ?? 0),
        ], $items);

        return md5((string) json_encode($normalised));
    }

    /** Upsert the client by email — the same dedup pattern as the public funnel. */
    private function upsertClient(array $client): Client
    {
        return Client::firstOrCreate(
            ['email' => $client['email']],
            [
                'name' => $client['name'],
                'phone' => $client['phone'] ?? null,
                'company' => $client['company'] ?? null,
            ],
        );
    }

    /** Persist the add-on rows (union across packages) for the quotation. */
    private function syncAddons(Quotation $quotation, array $addonKeys, PricingEngine $engine): void
    {
        $quotation->addons()->delete();

        if ($addonKeys === []) {
            return;
        }

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

    /**
     * Resolve the raw package entries into the canonical packages[] shape the whole
     * system reads: split each flat `modifiers` map onto the engine's
     * modifiers/scope_values and resolve its service_package_id.
     *
     * @param  list<array{package_key: string, modifiers: array, addon_keys: list<string>}>  $rawPackages
     * @return list<array{package_key: string, service_package_id: ?int, scope_values: array, modifiers: array, addon_keys: list<string>}>
     */
    private function resolveConnectorPackages(array $rawPackages, ConnectorCatalog $catalog, PricingEngine $engine): array
    {
        return array_map(function (array $rp) use ($catalog, $engine): array {
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
            'label' => (string) ($item['label'] ?? 'Item'),
            'description' => $item['description'] ?? null,
            'amount_myr' => (float) ($item['amount_myr'] ?? 0),
        ], array_filter($items, 'is_array')));
    }

    /**
     * Which of the three pricing modes the validated data describes — used to pick
     * the update's document-reseed policy before any write (buildDraft branches the
     * same way). detailed → self-priced sections; priced → one+ catalog packages;
     * bespoke → line items only.
     */
    private function draftMode(array $data): string
    {
        if (! empty($data['detailed']) && is_array($data['detailed'])) {
            return 'detailed';
        }

        return $this->rawPackages($data) === [] ? 'bespoke' : 'priced';
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
     * A slim list row (locked decision #5) — enough to identify and triage a
     * quotation from chat, no form_payload / document. Full detail is show().
     */
    protected static function listRow(Quotation $quotation, PricingEngine $engine): array
    {
        $document = is_array($quotation->document) ? $quotation->document : [];
        $hasEta = (int) $quotation->estimate_eta_value > 0;

        return [
            'reference_code' => $quotation->reference_code,
            'status' => $quotation->status,
            'created_via' => $document['created_via'] ?? ($quotation->normalizedForm()['source_meta']['created_via'] ?? null),
            'client' => [
                'name' => $quotation->name,
                'email' => $quotation->email,
                'company' => $quotation->company,
            ],
            'layout' => $document['layout'] ?? 'standard',
            'package_key' => $quotation->package_key,
            'package_label' => $quotation->package_key
                ? $engine->packageName($quotation->package_key)
                : ($document['project'] ?? null),
            'estimate' => [
                'min_myr' => (float) $quotation->estimate_min_myr,
                'max_myr' => (float) $quotation->estimate_max_myr,
                'eta_value' => $hasEta ? (int) $quotation->estimate_eta_value : null,
                'eta_unit' => $hasEta ? $quotation->estimate_eta_unit : null,
            ],
            'submitted_at' => $quotation->submitted_at?->toISOString(),
            'created_at' => $quotation->created_at?->toISOString(),
            'admin_url' => rtrim((string) config('services.frontend.url'), '/')."/admin/quotations/{$quotation->id}",
        ];
    }

    /**
     * The connector-facing projection of a full quotation — the draft as the AI
     * (and the admin reviewing it) needs to see it. Kept deliberately narrow: no
     * public_token, no internal audit fields.
     */
    protected static function connectorView(Quotation $quotation): array
    {
        $document = is_array($quotation->document) ? $quotation->document : [];
        $hasEta = (int) $quotation->estimate_eta_value > 0;
        // created_via via the normalizer (handles every legacy shape); last_updated_via
        // is a v3-only field the connector writes, read straight from the raw payload.
        $createdVia = $quotation->normalizedForm()['source_meta']['created_via'] ?? null;
        $rawMeta = is_array($quotation->form_payload['source_meta'] ?? null) ? $quotation->form_payload['source_meta'] : [];

        return [
            'reference_code' => $quotation->reference_code,
            'status' => $quotation->status,
            'source' => $quotation->source,
            'created_via' => $document['created_via'] ?? $createdVia,
            'last_updated_via' => $rawMeta['last_updated_via'] ?? null,
            'last_updated_at' => $rawMeta['last_updated_at'] ?? null,
            'client' => [
                'name' => $quotation->name,
                'email' => $quotation->email,
                'phone' => $quotation->phone,
                'company' => $quotation->company,
            ],
            'package_key' => $quotation->package_key,
            'layout' => $document['layout'] ?? 'standard',
            'project' => $document['project'] ?? null,
            'intro' => $document['intro'] ?? null,
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
            'updated_at' => $quotation->updated_at?->toISOString(),
        ];
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
