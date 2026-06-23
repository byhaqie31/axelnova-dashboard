<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminQuotationRequest;
use App\Http\Resources\QuotationResource;
use App\Jobs\SendClientQuoteEmail;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Quotation;
use App\Services\Quoting\EstimateResult;
use App\Services\Quoting\PricingEngine;
use App\Services\Quoting\QuoteRequestInput;
use App\Support\DocumentType;
use App\Support\ReferenceCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuotationsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Quotation::with('addons')->latest('submitted_at');

        // Quotations view excludes 'accepted' by default — accepted ones produced an order
        // and live on the Orders page. Caller can pass ?include_accepted=1 or ?status=accepted.
        if (! $request->boolean('include_accepted') && ! $request->filled('status')) {
            $query->where('status', '!=', 'accepted');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('reference_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('submitted_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('submitted_at', '<=', $request->date_to);
        }

        return QuotationResource::collection($query->paginate(20));
    }

    public function show(Quotation $quotation): QuotationResource
    {
        $quotation->load('addons', 'order');

        return new QuotationResource($quotation);
    }

    public function store(AdminQuotationRequest $request): QuotationResource
    {
        $engine = PricingEngine::active();
        $data = $request->validated();

        $quotation = DB::transaction(function () use ($data, $engine) {
            $client = $this->resolveClient($data);
            $input = $this->buildInput($client, $data);
            $estimate = $input->packageKey ? $engine->calculate($input) : null;

            $quotation = Quotation::create(array_merge(
                $this->pricedAttributes($client, $input, $engine, $estimate, $data),
                [
                    'reference_code' => ReferenceCodeGenerator::generate(DocumentType::Quotation),
                    'source' => ! empty($data['inquiry_id']) ? 'inquiry' : 'admin',
                    'public_token' => Str::random(48),
                    'status' => 'draft',
                    'submitted_at' => now(),
                ],
            ));

            $this->syncAddons($quotation, $input->addonKeys, $engine);

            if (! empty($data['inquiry_id'])) {
                Inquiry::where('id', $data['inquiry_id'])
                    ->update(['quotation_id' => $quotation->id, 'status' => 'quoted']);
            }

            return $quotation;
        });

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
            $input = $this->buildInput($client, $data);
            $estimate = $input->packageKey ? $engine->calculate($input) : null;

            $quotation->update($this->pricedAttributes($client, $input, $engine, $estimate, $data));
            $this->syncAddons($quotation, $input->addonKeys, $engine);
        });

        return new QuotationResource($quotation->load('addons', 'order'));
    }

    public function send(Quotation $quotation): JsonResponse
    {
        if (! $quotation->public_token) {
            $quotation->public_token = Str::random(48);
        }
        $quotation->status = 'sent';
        $quotation->sent_at = now();
        $quotation->save();

        SendClientQuoteEmail::dispatch($quotation->id);

        Inquiry::where('quotation_id', $quotation->id)
            ->where('status', '!=', 'quoted')
            ->update(['status' => 'quoted']);

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

        $quotation->update(['status' => $request->status]);

        return response()->json(['message' => 'Status updated.', 'status' => $quotation->status]);
    }

    public function accept(Request $request, Quotation $quotation): JsonResponse
    {
        if ($quotation->status === 'accepted') {
            return response()->json(['message' => 'Already accepted.', 'order_id' => $quotation->order?->id], 422);
        }

        if (! $quotation->client_id) {
            return response()->json(['message' => 'Quotation has no client linked.'], 422);
        }

        $order = DB::transaction(function () use ($quotation) {
            $quotation->update(['status' => 'accepted']);

            return Order::create([
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
        });

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

    private function buildInput(Client $client, array $data): QuoteRequestInput
    {
        return new QuoteRequestInput(
            name: $client->name,
            email: $client->email,
            phone: $client->phone ?? '',
            company: $client->company,
            packageKey: $data['package_key'] ?? null,
            modifiers: $data['modifiers'] ?? [],
            addonKeys: $data['addon_keys'] ?? [],
            rush: (bool) ($data['rush'] ?? false),
        );
    }

    /** The shared attribute set written on both create and update (the re-priced quotation). */
    private function pricedAttributes(Client $client, QuoteRequestInput $input, PricingEngine $engine, ?EstimateResult $estimate, array $data): array
    {
        $document = $data['document'] ?? null;

        // A detailed quote is priced by the sections the client actually sees, not
        // the engine. Stamp the agreed total as the stored estimate (min == max)
        // so the admin list, the order value, and the PDF all agree. Standard
        // quotes (and detailed quotes with no priced sections) keep the engine range.
        $detailedTotal = Quotation::sumDetailedSections($document);
        $minMyr = $detailedTotal ?? ($estimate?->minMyr ?? 0);
        $maxMyr = $detailedTotal ?? ($estimate?->maxMyr ?? 0);

        return [
            'client_id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'company' => $client->company,
            'package_key' => $input->packageKey ?: null,
            'pricing_config_id' => $engine->getConfig()->id,
            'form_payload' => array_merge($data['form_payload'] ?? [], [
                'package_key' => $input->packageKey ?: null,
                'modifiers' => $input->modifiers,
                'addon_keys' => $input->addonKeys,
                'rush' => $input->rush,
                'breakdown' => $estimate?->breakdown ?? [],
            ]),
            'document' => $document,
            'estimate_min_myr' => $minMyr,
            'estimate_max_myr' => $maxMyr,
            'estimate_eta_value' => $estimate?->etaValue ?? 0,
            'estimate_eta_unit' => $estimate?->etaUnit ?? 'week',
        ];
    }

    private function syncAddons(Quotation $quotation, array $addonKeys, PricingEngine $engine): void
    {
        $quotation->addons()->delete();
        $addonDefs = $engine->getConfig()->config['addons'] ?? [];

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
