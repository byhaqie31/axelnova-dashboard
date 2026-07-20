<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminQuotationRequest;
use App\Http\Requests\Admin\ClientLinkRequest;
use App\Http\Resources\QuotationResource;
use App\Jobs\SendClientQuoteEmail;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Referral;
use App\Services\Quoting\DocumentMapper;
use App\Services\Quoting\DocumentSeeder;
use App\Services\Quoting\MultiEstimateResult;
use App\Services\Quoting\PricingEngine;
use App\Services\Quoting\QuotationIndexQuery;
use App\Services\Referrals\ReferralAttributionService;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QuotationsController extends Controller
{
    /**
     * Live quotation-document preview from the builder's current draft, WITHOUT
     * persisting. Hydrates a transient Quotation and runs the same DocumentMapper
     * the PDF uses, so the preview matches the eventual document.
     */
    public function preview(Request $request): JsonResponse
    {
        $quotation = new Quotation;
        $quotation->forceFill([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'company' => $request->input('company'),
            'package_key' => $request->input('package_key'),
            'reference_code' => 'DRAFT',
            'document' => $request->input('document', []),
            'form_payload' => $request->input('form_payload', []),
            'expires_at' => $request->input('expires_at'),
        ]);
        // Avoid a lazy-load on the unsaved model when the standard layout maps items.
        $quotation->setRelation('addons', collect());

        return response()->json(DocumentMapper::toDocumentData($quotation));
    }

    /**
     * Seed a standard `document` from the builder's current scope, WITHOUT
     * persisting. Runs the SAME shared DocumentSeeder the MCP connector uses, so
     * the admin "Seed line items from scope" button and a connector draft produce
     * byte-identical documents. Accepts the canonical packages[] (or single-package
     * sugar); returns `{ document, assumptions }` for the builder to merge in.
     */
    public function seedDocument(Request $request): JsonResponse
    {
        $config = PricingEngine::cachedFrontendConfig();
        $validPackageKeys = array_keys($config['base_packages'] ?? []);
        $validAddonKeys = array_keys($config['addons'] ?? []);

        $data = $request->validate([
            'rush' => ['boolean'],
            'packages' => ['nullable', 'array'],
            'packages.*.package_key' => ['required_with:packages', 'string', Rule::in($validPackageKeys)],
            'packages.*.scope_values' => ['nullable', 'array'],
            'packages.*.modifiers' => ['nullable', 'array'],
            'packages.*.addon_keys' => ['nullable', 'array'],
            'packages.*.addon_keys.*' => ['string', Rule::in($validAddonKeys)],
            'package_key' => ['nullable', 'string', Rule::in($validPackageKeys)],
            'scope_values' => ['nullable', 'array'],
            'modifiers' => ['nullable', 'array'],
            'addon_keys' => ['nullable', 'array'],
            'addon_keys.*' => ['string', Rule::in($validAddonKeys)],
        ]);

        $engine = PricingEngine::active();
        $packages = $this->resolvePackages($data, $engine);
        $rush = (bool) ($data['rush'] ?? false);
        $estimate = $engine->calculateMulti($packages, $rush);

        return response()->json((new DocumentSeeder($engine))->seed($estimate, $rush));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        // Self-heal overdue sent quotes before listing, so 'expired' is accurate
        // (and filterable) without a scheduler.
        Quotation::expireOverdue();

        // Search + status + date-range + newest-first live in the shared query
        // object (the connector's list endpoint uses the SAME one).
        $query = QuotationIndexQuery::fromAdminRequest($request)->builder()->with('addons');

        return QuotationResource::collection($query->paginate(20));
    }

    public function show(Quotation $quotation): QuotationResource
    {
        // Lazy expiry for this one record (the list sweep won't have run on a deep link).
        if ($quotation->isOverdue()) {
            $quotation->update(['status' => 'expired']);
        }

        $quotation->load('addons', 'order', 'updatedBy', 'referrer');

        return new QuotationResource($quotation);
    }

    public function store(AdminQuotationRequest $request): QuotationResource
    {
        $engine = PricingEngine::active();
        $data = $request->validated();

        $quotation = DB::transaction(function () use ($data, $engine) {
            $client = $this->resolveClient($data);
            $packages = $this->resolvePackages($data, $engine);
            $rush = (bool) ($data['rush'] ?? false);
            $estimate = $packages !== [] ? $engine->calculateMulti($packages, $rush) : null;

            $quotation = Quotation::create(array_merge(
                $this->pricedAttributes($client, $engine, $packages, $rush, $estimate, $data, 'admin'),
                [
                    'reference_code' => ReferenceCodeGenerator::generate(DocumentType::Quotation),
                    'source' => ! empty($data['inquiry_id']) ? 'inquiry' : 'admin',
                    'public_token' => Str::random(48),
                    'status' => 'draft',
                    'submitted_at' => now(),
                ],
            ));

            $this->syncAddons($quotation, $this->allAddonKeys($packages), $engine);

            if (! empty($data['inquiry_id'])) {
                $inquiry = Inquiry::find($data['inquiry_id']);
                if ($inquiry) {
                    $inquiry->update(['quotation_id' => $quotation->id, 'status' => 'quoted']);
                    app(ReferralAttributionService::class)->attribute($quotation, $inquiry);
                }
            }

            return $quotation;
        });

        $quotation->logActivity('quotation.created', ['reference_code' => $quotation->reference_code]);

        return new QuotationResource($quotation->load('addons', 'order'));
    }

    public function update(AdminQuotationRequest $request, Quotation $quotation): JsonResponse|QuotationResource
    {
        if ($quotation->status !== 'draft') {
            return response()->json(['message' => 'Only draft quotations can be edited.'], 422);
        }

        $engine = PricingEngine::active();
        $data = $request->validated();

        DB::transaction(function () use ($data, $engine, $quotation) {
            $client = $this->resolveClient($data);
            $packages = $this->resolvePackages($data, $engine);
            $rush = (bool) ($data['rush'] ?? false);
            $estimate = $packages !== [] ? $engine->calculateMulti($packages, $rush) : null;
            // created_via is sticky — an admin editing a connector draft keeps its
            // "Via connector" provenance on the Draft context panel.
            $createdVia = $quotation->normalizedForm()['source_meta']['created_via'] ?? 'admin';

            $quotation->update($this->pricedAttributes($client, $engine, $packages, $rush, $estimate, $data, $createdVia));
            $this->syncAddons($quotation, $this->allAddonKeys($packages), $engine);
        });

        $quotation->logActivity('quotation.updated');

        return new QuotationResource($quotation->load('addons', 'order'));
    }

    /**
     * Soft-delete a quotation (portal-only — never exposed to the MCP connector,
     * where destructive actions stay human-only). A quotation with an order
     * attached cannot be deleted (the order FK is restrict, and the order is the
     * money record) — refused with a 409 naming the order so the UI can link to it.
     * Soft delete: the row disappears from every list + read, recoverable from the DB.
     *
     * Because a soft delete does NOT fire the `nullOnDelete` FKs, we untangle the
     * linked flow by hand — any inquiry or referral anchored to this quote is
     * unlinked (the same reset the manual unlink/untie actions perform) so nothing
     * is left pointing at a deleted row.
     */
    public function destroy(Quotation $quotation): JsonResponse
    {
        $order = $quotation->order;
        if ($order) {
            return response()->json([
                'message' => "This quotation can't be deleted — it was accepted into order {$order->order_number}. Delete or unwind that order first.",
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ], 409);
        }

        $reference = $quotation->reference_code;

        [$unlinkedInquiries, $untiedReferrals] = DB::transaction(function () use ($quotation): array {
            // Inquiry → back to 'reviewing' (needs a fresh quote), as unlinkQuotation does.
            $inquiries = Inquiry::where('quotation_id', $quotation->id)
                ->update(['quotation_id' => null, 'status' => 'reviewing']);

            // Referral → back to a plain 'new' claim, as untieQuotation does.
            $referrals = Referral::where('quotation_id', $quotation->id)
                ->update(['quotation_id' => null, 'status' => 'new']);

            $quotation->logActivity('quotation.deleted', [
                'reference_code' => $quotation->reference_code,
                'unlinked_inquiries' => $inquiries,
                'untied_referrals' => $referrals,
            ]);
            $quotation->delete();

            return [$inquiries, $referrals];
        });

        return response()->json([
            'message' => "Quotation {$reference} deleted.",
            'unlinked_inquiries' => $unlinkedInquiries,
            'untied_referrals' => $untiedReferrals,
        ]);
    }

    public function send(Request $request, Quotation $quotation): JsonResponse
    {
        if (! $quotation->public_token) {
            $quotation->public_token = Str::random(48);
        }
        $quotation->status = 'sent';
        $quotation->sent_at = now();
        // Keep a custom validity date if the builder set one; otherwise default to
        // sent_at + valid_for_days. Drives lazy auto-expiry and the PDF "valid until".
        if (! $quotation->expires_at) {
            $validForDays = (int) ($quotation->pricingConfig?->config['valid_for_days'] ?? 30);
            $quotation->expires_at = now()->addDays($validForDays);
        }
        $quotation->save();

        // Email delivery is the default; the "download PDF" channel marks the
        // quote sent without emailing (the admin delivers the file themselves).
        if ($request->boolean('email', true)) {
            SendClientQuoteEmail::dispatch($quotation->id);
        }

        Inquiry::where('quotation_id', $quotation->id)
            ->where('status', '!=', 'quoted')
            ->update(['status' => 'quoted']);

        $quotation->logActivity('quotation.sent', ['emailed' => $request->boolean('email', true)]);

        return response()->json([
            'message' => 'Quotation sent to the client.',
            'data' => new QuotationResource($quotation->load('addons', 'order')),
        ]);
    }

    public function updateStatus(Request $request, Quotation $quotation): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:draft,sent,accepted,rejected,expired'],
        ]);

        $from = $quotation->status;
        $quotation->update(['status' => $request->status]);
        $quotation->logActivity('quotation.status', ['from' => $from, 'to' => $quotation->status]);

        return response()->json(['message' => 'Status updated.', 'status' => $quotation->status]);
    }

    /**
     * Set (or clear) a custom validity date on a sent/expired quote. Extending into
     * the future re-activates an expired quote; pulling it into the past expires a
     * sent one — so status and date stay in agreement after a manual change.
     */
    public function setExpiry(Request $request, Quotation $quotation): JsonResponse
    {
        $request->validate(['expires_at' => ['nullable', 'date']]);

        $expiresAt = $request->date('expires_at')?->endOfDay();
        $quotation->expires_at = $expiresAt;

        if ($quotation->status === 'expired' && ($expiresAt === null || $expiresAt->isFuture())) {
            $quotation->status = 'sent';
        } elseif ($quotation->status === 'sent' && $expiresAt !== null && $expiresAt->isPast()) {
            $quotation->status = 'expired';
        }

        $quotation->save();

        return response()->json([
            'message' => 'Expiry updated.',
            'data' => new QuotationResource($quotation->load('addons', 'order')),
        ]);
    }

    /**
     * Correct a mis-matched quotation's client. A quotation serves its OWN contact
     * snapshot (QuotationResource), so re-linking must both re-point client_id AND
     * refresh the snapshot from the new client (relinkToClient does both). Available
     * regardless of lifecycle status — the records needing correction may be sent or
     * accepted, and this touches only the client link, not pricing or the document.
     */
    public function updateClient(ClientLinkRequest $request, Quotation $quotation): JsonResponse
    {
        [$client, $linkedExisting] = Client::resolveForRelink($request->validated());

        $quotation->relinkToClient($client);
        $quotation->logActivity('quotation.client', ['client_id' => $client->id]);

        return response()->json([
            'message' => $linkedExisting ? 'Linked to the existing client.' : 'Client updated.',
            'linked_existing' => $linkedExisting,
            'data' => new QuotationResource($quotation->load('addons', 'order', 'referrer', 'updatedBy')),
        ]);
    }

    public function accept(Request $request, Quotation $quotation): JsonResponse
    {
        // Founder-only: converting an accepted quote into an order.
        Gate::authorize('accept-quote');

        if ($quotation->status === 'accepted') {
            return response()->json(['message' => 'Already accepted.', 'order_id' => $quotation->order?->id], 422);
        }

        if (! $quotation->client_id) {
            return response()->json(['message' => 'Quotation has no client linked.'], 422);
        }

        $request->validate([
            'commission_pct' => ['nullable', 'integer', 'min:5', 'max:15'],
        ]);

        $order = DB::transaction(function () use ($request, $quotation) {
            $quotation->update(['status' => 'accepted']);

            $order = Order::create([
                'order_number' => ReferenceCodeGenerator::generate(DocumentType::Order),
                'quotation_id' => $quotation->id,
                'client_id' => $quotation->client_id,
                'value_min_myr' => $quotation->estimate_min_myr,
                'value_max_myr' => $quotation->estimate_max_myr,
                'final_amount_myr' => $quotation->finalAmount(),
                'deposit_pct' => $quotation->depositPct(),
                'amount_paid_myr' => 0,
                'due_at' => $quotation->dueDateFrom(),
                'status' => 'pending',
            ]);

            $referral = Referral::where('quotation_id', $quotation->id)->first();
            if ($referral) {
                $referral->update([
                    'commission_pct' => $request->integer('commission_pct') ?: $referral->commission_tier_pct,
                ]);
            }

            return $order;
        });

        $quotation->logActivity('quotation.accepted', ['order_number' => $order->order_number]);
        $order->logActivity('order.created', ['from_quotation' => $quotation->reference_code]);

        return response()->json([
            'message' => 'Quotation accepted. Order created.',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }

    /** Resolve an existing client by id, or upsert one by email (same as the public funnel). */
    private function resolveClient(array $data): Client
    {
        if (! empty($data['client_id'])) {
            return Client::findOrFail($data['client_id']);
        }

        return Client::firstOrCreate(
            ['email' => $data['email']],
            ['name' => $data['name'], 'phone' => $data['phone'] ?? null, 'company' => $data['company'] ?? null],
        );
    }

    /**
     * Resolve the validated request into the canonical packages[] shape, filling
     * each entry's service_package_id from the catalog (null for legacy JSON-only
     * packages that have no DB row). Prefers the canonical packages[]; falls back
     * to the single-package sugar (package_key + scope_values/modifiers/addon_keys).
     * Unknown package keys are already rejected by AdminQuotationRequest.
     *
     * @return list<array{package_key: string, service_package_id: ?int, scope_values: array, modifiers: array, addon_keys: list<string>}>
     */
    private function resolvePackages(array $data, PricingEngine $engine): array
    {
        $raw = ! empty($data['packages']) && is_array($data['packages'])
            ? $data['packages']
            : (filled($data['package_key'] ?? null)
                ? [[
                    'package_key' => $data['package_key'],
                    'scope_values' => $data['scope_values'] ?? [],
                    'modifiers' => $data['modifiers'] ?? [],
                    'addon_keys' => $data['addon_keys'] ?? [],
                ]]
                : []);

        return array_values(array_map(function ($p) use ($engine): array {
            $key = (string) ($p['package_key'] ?? '');

            return [
                'package_key' => $key,
                'service_package_id' => $engine->packageId($key),
                'scope_values' => (array) ($p['scope_values'] ?? []),
                'modifiers' => (array) ($p['modifiers'] ?? []),
                'addon_keys' => array_values((array) ($p['addon_keys'] ?? [])),
            ];
        }, $raw));
    }

    /**
     * Union of add-on keys across every package (add-ons persist per-quotation, not
     * per-package, so a multi-package quote dedupes them).
     *
     * @param  list<array{addon_keys?: list<string>}>  $packages
     * @return list<string>
     */
    private function allAddonKeys(array $packages): array
    {
        return collect($packages)
            ->flatMap(fn (array $p): array => $p['addon_keys'] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    /** The shared attribute set written on both create and update (the re-priced quotation). */
    private function pricedAttributes(Client $client, PricingEngine $engine, array $packages, bool $rush, ?MultiEstimateResult $estimate, array $data, string $createdVia): array
    {
        $document = $data['document'] ?? null;

        // A detailed quote is priced by the sections the client actually sees, not
        // the engine. Stamp the agreed total as the stored estimate (min == max)
        // so the admin list, the order value, and the PDF all agree. Standard
        // quotes (and detailed quotes with no priced sections) keep the engine range.
        $detailedTotal = Quotation::sumDetailedSections($document);
        $minMyr = $detailedTotal ?? ($estimate?->minMyr ?? 0);
        $maxMyr = $detailedTotal ?? ($estimate?->maxMyr ?? 0);
        $first = $packages[0] ?? null;

        return [
            'client_id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'company' => $client->company,
            // Scalar columns carry the FIRST package (list display + back-compat).
            'package_key' => $first['package_key'] ?? null,
            'service_package_id' => $first['service_package_id'] ?? null,
            'pricing_config_id' => $engine->getConfig()->id,
            // Canonical multi-package form_payload (see FormPayloadNormalizer).
            'form_payload' => array_merge($data['form_payload'] ?? [], [
                'packages' => $packages,
                'rush' => $rush,
                'breakdown' => $estimate?->breakdown ?? [],
                'source_meta' => ['created_via' => $createdVia],
            ]),
            'document' => $document,
            'estimate_min_myr' => $minMyr,
            'estimate_max_myr' => $maxMyr,
            'estimate_eta_value' => $estimate?->etaValue ?? 0,
            'estimate_eta_unit' => $estimate?->etaUnit ?? 'week',
            // Custom validity date (optional). Normalised to end-of-day so the quote
            // stays valid through the whole chosen date, not until its midnight.
            'expires_at' => isset($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null,
        ];
    }

    private function syncAddons(Quotation $quotation, array $addonKeys, PricingEngine $engine): void
    {
        $quotation->addons()->delete();
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
}
